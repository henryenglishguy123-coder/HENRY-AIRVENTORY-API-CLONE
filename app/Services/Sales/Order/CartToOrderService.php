<?php

namespace App\Services\Sales\Order;

use App\Enums\Order\OrderStatus;
use App\Enums\Order\PaymentStatus;
use App\Models\Catalog\Product\CatalogProduct;
use App\Models\Catalog\Product\CatalogProductInventory;
use App\Models\Catalog\Product\CatalogProductPriceWithMargin;
use App\Models\Catalog\Product\CatalogProductPrintingPrice;
use App\Models\Customer\Cart\Cart;
use App\Models\Customer\Cart\CartAddress;
use App\Models\Customer\Cart\CartItem;
use App\Models\Customer\Designer\VendorDesignLayer;
use App\Models\Customer\Designer\VendorDesignLayerImage;
use App\Models\Customer\VendorMeta;
use App\Models\Factory\FactoryShippingRate;
use App\Models\Factory\HangTag;
use App\Models\Factory\PackagingLabel;
use App\Models\Location\Country;
use App\Models\Location\State;
use App\Models\Sales\Order\Address\SalesOrderAddress;
use App\Models\Sales\Order\Item\SalesOrderItem;
use App\Models\Sales\Order\Item\SalesOrderItemDesign;
use App\Models\Sales\Order\Item\SalesOrderItemOption;
use App\Models\Sales\Order\OrderSequence;
use App\Models\Sales\Order\SalesOrder;
use App\Models\Sales\Order\SalesOrderSource;
use App\Models\Sales\Order\Branding\SalesOrderBranding;
use App\Services\StoreConfigService;
use App\Services\Tax\TaxResolverService;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class CartToOrderService
{
    public function __construct(
        protected TaxResolverService $taxResolverService,
        protected OrderDiscountService $orderDiscountService
    ) {}

    /**
     * Convert cart into one or multiple orders
     *
     * @return Collection<SalesOrder>
     *
     * @throws Exception
     */
    public function convert(Cart $cart, string $source = 'web'): Collection
    {
        $cart->load(['discount', 'address', 'items.variant', 'items.packagingLabel', 'items.hangTag', 'sources']);
        if ($cart->items->isEmpty()) {
            throw new Exception(__('Cart is empty.'));
        }

        $orders = collect();
        $allowSplit = $this->shouldSplitOrders($cart->vendor_id);
        $groups = $allowSplit ? $cart->items->groupBy('fulfillment_factory_id') : collect([$cart->items]);
        DB::transaction(function () use ($cart, $source, $groups, &$orders) {
            $this->validateInventory($cart);

            foreach ($groups as $items) {
                $factoryId = $items->first()->fulfillment_factory_id;
                $orders->push(
                    $this->createSalesOrder(
                        $cart,
                        $factoryId,
                        $items,
                        $source,
                    )
                );
            }

            $cart->status = 'converted';
            $cart->save();
        });

        return $orders;
    }

    /**
     * Create SalesOrder + items + addresses
     */
    protected function createSalesOrder(Cart $cart, ?int $factoryId, Collection $items, string $source): SalesOrder
    {
        // 0. Resolve external source (e.g., Shopify) metadata from cart_sources
        $cartSource = $cart->sources
            ->where('platform', $source)
            ->sortByDesc('id')
            ->first();

        // 1. Build Items & Calculate Item Totals
        $orderItemsPayload = $this->buildOrderItems($items, $factoryId);
        $orderItems = collect($orderItemsPayload);

        $totals = $this->calculateItemTotals($orderItems);

        // 2. Calculate Shipping
        $shippingData = $this->calculateShippingForGroup($items, $cart, $factoryId);
        $shippingCost = $shippingData['cost'];
        $shippingTaxInfo = $this->calculateShippingTaxAndDescription($cart, $shippingCost);
        $shippingTax = $shippingTaxInfo['shipping_tax'];

        // 3. Reserve Inventory
        $this->reserveInventory($items);

        // 4. Base Subtotals (Sum of items)
        $baseSubtotal = $totals['base_subtotal'];
        $baseSubtotalIncMargin = $totals['base_subtotal_inc_margin'];

        // 5. Calculate Discount
        $allocatedDiscount = 0;
        $allocatedDiscountIncMargin = 0;
        $discountCouponCode = $cart->discount ? $cart->discount->code : null;

        if ($discountCouponCode) {
            try {
                // Calculate discount for base subtotal
                $discountResult = $this->orderDiscountService->checkCoupon($discountCouponCode, $baseSubtotal);
                $allocatedDiscount = $discountResult['amount'];

                // Calculate discount for subtotal with margin
                $discountResultMargin = $this->orderDiscountService->checkCoupon($discountCouponCode, $baseSubtotalIncMargin);
                $allocatedDiscountIncMargin = $discountResultMargin['amount'];
            } catch (ValidationException $e) {
                // Ignore invalid coupons during conversion
                Log::warning('Invalid coupon during order conversion', [
                    'coupon' => $discountCouponCode,
                    'cart_id' => $cart->id,
                    'error' => $e->getMessage(),
                ]);
                $allocatedDiscount = 0;
                $allocatedDiscountIncMargin = 0;
            }
        }

        // 6. Calculate Net Subtotals (After Discount)
        $netSubtotal = max(0, $baseSubtotal - $allocatedDiscount);
        $netSubtotalIncMargin = max(0, $baseSubtotalIncMargin - $allocatedDiscountIncMargin);

        // 7. Calculate Tax on Net Subtotals
        $netSubtotalTaxData = $this->calculateTaxForCart($cart, $netSubtotal);
        $netSubtotalTax = $netSubtotalTaxData['tax_amount'];

        $netSubtotalIncMarginTaxData = $this->calculateTaxForCart($cart, $netSubtotalIncMargin);
        $netSubtotalIncMarginTax = $netSubtotalIncMarginTaxData['tax_amount'];

        // 8. Calculate Grand Totals
        $grandTotal = $netSubtotal + $netSubtotalTax + $shippingCost + $shippingTax;
        $grandTotalIncMargin = $netSubtotalIncMargin + $netSubtotalIncMarginTax + $shippingCost + $shippingTax;

        // 9. Create Order
        $order = SalesOrder::create([
            'order_number' => $this->generateOrderNumber(),
            'customer_id' => $cart->vendor_id,
            'factory_id' => $factoryId ?: null,
            'cart_id' => $cart->id,
            'order_status' => OrderStatus::Pending->value,
            'payment_status' => PaymentStatus::Pending->value,
            'shipping_method' => 'Standard',
            'payment_method' => 'N/A',
            'tax_description' => $shippingTaxInfo['description'],

            // Base Totals (Before Discount/Tax)
            'base_subtotal_before_discount' => $totals['base_subtotal_before_discount'],
            'base_subtotal' => $netSubtotal,
            'base_subtotal_tax' => $netSubtotalTax,
            'base_total' => $grandTotal,

            // With Margin
            'base_subtotal_inc_margin_before_discount' => $totals['base_subtotal_inc_margin_before_discount'],
            'base_subtotal_inc_margin' => $netSubtotalIncMargin,
            'base_subtotal_tax_inc_margin' => $netSubtotalIncMarginTax,
            'base_total_inc_margin' => $grandTotalIncMargin,

            // Discounts
            'base_discount' => $allocatedDiscount,
            'base_discount_inc_margin' => $allocatedDiscountIncMargin,

            // Shipping
            'shipping_subtotal' => $shippingCost,
            'shipping_subtotal_tax' => $shippingTax,
            'shipping_total' => $shippingCost + $shippingTax,

            // Grand Totals (Base)
            'grand_subtotal' => $netSubtotal,
            'grand_subtotal_tax' => $netSubtotalTax,
            'grand_total' => $grandTotal,

            // Grand Totals (Inc Margin)
            'grand_subtotal_inc_margin' => $netSubtotalIncMargin,
            'grand_subtotal_tax_inc_margin' => $netSubtotalIncMarginTax,
            'grand_total_inc_margin' => $grandTotalIncMargin,

            'discount_description' => $discountCouponCode,
            'delivery_date' => null,
            'remote_ip' => app()->runningInConsole() ? null : request()->ip(),
        ]);

        // Eager load variants with attributes for options creation
        $variantIds = collect($orderItemsPayload)->pluck('variant_id')->unique();
        $variantsWithAttributes = CatalogProduct::with([
            'attributes.option',
            'attributes.attribute.description',
        ])->whereIn('id', $variantIds)->get()->keyBy('id');

        foreach ($orderItemsPayload as $payload) {
            $itemBrandingData = $payload['_branding_data'] ?? null;
            unset($payload['_branding_data']);

            $payload['order_id'] = $order->id;
            $salesOrderItem = SalesOrderItem::create($payload);

            if (! empty($payload['template_id'])) {
                $this->createOrderItemDesigns(
                    $salesOrderItem,
                    (int) $payload['template_id']
                );
            }

            // Create Order Item Options
            if (isset($payload['variant_id']) && $variantsWithAttributes->has($payload['variant_id'])) {
                $this->createOrderItemOptions($salesOrderItem, $variantsWithAttributes->get($payload['variant_id']));
            }

            // Create Sales Order Branding
            if ($itemBrandingData) {
                $itemBrandingData['order_item_id'] = $salesOrderItem->id;
                SalesOrderBranding::create($itemBrandingData);
            }
        }

        if ($cart->address) {
            $this->createOrderAddress($order, $cart->address);
        }

        // 10. Link Source Info (if exists)
        if ($cartSource) {
            SalesOrderSource::create([
                'order_id' => $order->id,
                'platform' => $cartSource->platform,
                'source' => $cartSource->source,
                'source_order_id' => $cartSource->source_order_id,
                'source_order_number' => $cartSource->source_order_number,
                'source_created_at' => $cartSource->source_created_at,
                'payload' => $cartSource->payload,
            ]);
        }

        return $order;
    }

    /**
     * Validate inventory availability for all items
     */
    protected function validateInventory(Cart $cart): void
    {
        $variantIds = $cart->items->pluck('variant_id')->unique();
        $factoryIds = $cart->items->pluck('fulfillment_factory_id')->unique();

        $variants = CatalogProduct::whereIn('id', $variantIds)->get()->keyBy('id');

        $inventories = CatalogProductInventory::whereIn('product_id', $variantIds)
            ->whereIn('factory_id', $factoryIds)
            ->get();

        // Build inventory map: "productId_factoryId" => inventory
        $inventoryMap = [];
        foreach ($inventories as $inv) {
            $inventoryMap["{$inv->product_id}_{$inv->factory_id}"] = $inv;
        }

        foreach ($cart->items as $item) {
            if (! $variants->has($item->variant_id)) {
                throw new Exception(sprintf(
                    __('Variant not found for item %s (variant_id: %s)'),
                    $item->id,
                    $item->variant_id
                ));
            }

            $key = "{$item->variant_id}_{$item->fulfillment_factory_id}";
            $inventory = $inventoryMap[$key] ?? null;

            if ($inventory && $inventory->manage_inventory == 1) {
                if ($inventory->quantity < $item->qty) {
                    throw new Exception(sprintf(
                        __('Insufficient stock for product %s. Available: %d, Requested: %d'),
                        $item->product_title,
                        $inventory->quantity,
                        $item->qty
                    ));
                }
            }
        }
    }

    /**
     * Reserve (decrement) inventory
     */
    protected function reserveInventory(Collection $items): void
    {
        foreach ($items as $item) {
            $inventory = CatalogProductInventory::where('product_id', $item->variant_id)
                ->where('factory_id', $item->fulfillment_factory_id)
                ->lockForUpdate() // Lock to prevent race conditions
                ->first();

            if ($inventory && $inventory->manage_inventory == 1) {
                if ($inventory->quantity < $item->qty) {
                    throw new Exception(sprintf(
                        __('Insufficient stock for product %s during reservation.'),
                        $item->product_title
                    ));
                }
                $inventory->decrement('quantity', $item->qty);
            }
        }
    }

    protected function calculateShippingForGroup(Collection $items, Cart $cart, ?int $factoryId): array
    {
        if (! $cart->address || ! $cart->address->country_id || ! $factoryId) {
            return ['cost' => 0];
        }

        $country = Country::find($cart->address->country_id);
        if (! $country) {
            return ['cost' => 0];
        }

        $totalQty = $items->sum('qty');

        $variantIds = $items->pluck('variant_id')->unique();
        $variants = CatalogProduct::whereIn('id', $variantIds)->get()->keyBy('id');

        $totalWeight = $items->sum(function ($item) use ($variants) {
            $variant = $variants->get($item->variant_id);

            return (float) ($variant->weight ?? 0) * $item->qty;
        });

        $rate = FactoryShippingRate::where('factory_id', $factoryId)
            ->where('country_code', $country->iso2)
            ->where('min_qty', '<=', $totalQty)
            ->orderBy('min_qty', 'desc')
            ->orderBy('price', 'asc')
            ->first();

        if ($rate) {
            return ['cost' => $totalWeight * $rate->price];
        }
        Log::warning("No shipping rate found for factory $factoryId, defaulting to 0.");

        return ['cost' => 0];
    }

    protected function calculateShippingTaxAndDescription(Cart $cart, float $shipping): array
    {
        $countryId = $cart->address?->country_id;
        $stateCode = null;
        $postalCode = $cart->address?->postal_code;
        if ($cart->address && $cart->address->state_id) {
            $state = State::find($cart->address->state_id);
            $stateCode = $state?->iso2;
        }
        if (! $countryId) {
            return [
                'description' => null,
                'shipping_tax' => 0,
            ];
        }
        $taxRule = $this->taxResolverService->resolve($countryId, $stateCode, $postalCode);
        $taxDescription = $taxRule ? $taxRule->tax->code : null;
        $shippingTaxData = $this->taxResolverService->calculate($shipping, $countryId, $stateCode, $postalCode);
        $shippingTax = $shippingTaxData['amount'] ?? 0;

        return [
            'description' => $taxDescription,
            'shipping_tax' => $shippingTax,
        ];
    }

    /**
     * Build SalesOrderItem payloads
     */
    protected function buildOrderItems(Collection $items, ?int $factoryId): array
    {
        $weightUnit = app(StoreConfigService::class)->get('weight_unit');
        $variantIds = $items->pluck('variant_id')->unique();
        $templateIds = $items->pluck('template_id')->unique();

        $variants = CatalogProduct::whereIn('id', $variantIds)->get()->keyBy('id');

        $prices = CatalogProductPriceWithMargin::whereIn('catalog_product_id', $variantIds)
            ->where('factory_id', $factoryId)
            ->get()
            ->keyBy('catalog_product_id');

        // 2. Fetch all layers for these templates to calculate total technique prices
        // Use the new column name: vendor_design_template_id
        $vendorLayersByTemplate = VendorDesignLayer::whereIn('vendor_design_template_id', $templateIds)
            ->get()
            ->groupBy('vendor_design_template_id');

        $factoryPackaging = $factoryId ? PackagingLabel::where('factory_id', $factoryId)->orderBy('id', 'desc')->first() : null;
        $factoryHangTag = $factoryId ? HangTag::where('factory_id', $factoryId)->orderBy('id', 'desc')->first() : null;

        $orderItems = [];

        foreach ($items as $item) {
            $variant = $variants->get($item->variant_id);

            if (! $variant) {
                throw new Exception("Variant not found for item {$item->id} (variant_id: {$item->variant_id})");
            }

            $priceModel = $prices->get($item->variant_id);

            if (! $priceModel) {
                $factoryPrice = $item->unit_price;
            } else {
                $factoryPrice = $priceModel->base_sale_price
                    ?? $priceModel->base_regular_price
                    ?? 0;
            }
            $printingTechniques = [];
            $printingCost = 0;
            foreach ($vendorLayersByTemplate->get($item->template_id, collect()) as $layer) {
                $price = CatalogProductPrintingPrice::with('printingTechnique')
                    ->where([
                        'catalog_product_id' => $item->product_id,
                        'layer_id' => $layer->catalog_design_template_layer_id,
                        'printing_technique_id' => $layer->technique_id,
                    ])
                    ->first();

                if (! $price || ! $price->printingTechnique) {
                    continue;
                }

                $printingTechniques[] = [
                    'technique' => $price->printingTechnique->name,
                    'price' => (float) $price->price,
                ];

                $printingCost += (float) $price->price;
            }
            $marginPercentage = 0;
            if ($priceModel instanceof CatalogProductPriceWithMargin) {
                $marginPercentage = $priceModel->getApplicableMarkupPercentage();
            } elseif ($priceModel) {
                $specificMarkup = $priceModel->specific_markup ?? null;
                $marginPercentage = (! is_null($specificMarkup) && is_numeric($specificMarkup))
                    ? (float) $specificMarkup
                    : CatalogProductPriceWithMargin::getGlobalMarkup();
            }
            $rowPrice = $factoryPrice + $printingCost;
            $marginDecimal = max(0, (float) $marginPercentage) / 100;
            if ($marginDecimal >= 1) {
                $rowPriceIncMargin = $rowPrice; // avoid division by zero
            } else {
                $rowPriceIncMargin = $rowPrice / (1 - $marginDecimal);
            }

            // Add branding pricing to the row level (using per-item margin)
            $brandingData = $this->resolveItemBrandingData($item, $marginDecimal, $factoryPackaging, $factoryHangTag);
            $brandingBase = 0;
            $brandingIncMargin = 0;

            if ($brandingData) {
                $brandingBase = ($brandingData['packaging_base_price'] ?? 0) + ($brandingData['hang_tag_base_price'] ?? 0);
                $brandingIncMargin = ($brandingData['packaging_base_price'] ?? 0) + ($brandingData['packaging_margin_price'] ?? 0) + ($brandingData['hang_tag_base_price'] ?? 0) + ($brandingData['hang_tag_margin_price'] ?? 0);

                $rowPrice += $brandingBase;
                $rowPriceIncMargin += $brandingIncMargin;
            }

            $margin = $rowPriceIncMargin - $rowPrice;
            $qty = (int) $item->qty;
            $taxRate = (float) ($item->tax_rate ?? 0);

            $subtotal = $rowPrice * $qty;
            $subtotalIncMargin = $rowPriceIncMargin * $qty;

            $taxableAmount = $subtotal;
            $taxableAmountIncMargin = $subtotalIncMargin;
            $subtotalTax = ($taxableAmount * $taxRate) / 100;
            $subtotalIncMarginTax = ($taxableAmountIncMargin * $taxRate) / 100;

            $orderItems[] = [
                'product_id' => $item->product_id,
                'product_name' => $item->product_title,
                'catalog_name' => $item->catalog_name ?? null,
                'variant_id' => $item->variant_id,
                'template_id' => $item->template_id,
                'sku' => $item->sku,

                'weight_unit' => $weightUnit,
                'unit_weight' => $variant->weight ?? 0,

                'factory_price' => $factoryPrice,
                'margin_price' => $margin,

                'printing_description' => array_values($printingTechniques),
                'printing_cost' => $printingCost,

                'branding_cost' => $brandingBase,
                'branding_cost_inc_margin' => $brandingIncMargin,

                'row_price' => $rowPrice,
                'row_price_inc_margin' => $rowPriceIncMargin,

                'qty' => $qty,
                'tax_rate' => $taxRate,

                'subtotal' => $subtotal,
                'subtotal_tax' => $subtotalTax,
                'subtotal_inc_margin' => $subtotalIncMargin,
                'subtotal_inc_margin_tax' => $subtotalIncMarginTax,
                'grand_total' => $subtotal + $subtotalTax,
                'grand_total_inc_margin' => $subtotalIncMargin + $subtotalIncMarginTax,

                '_branding_data' => $brandingData,

                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        return $orderItems;
    }

    /**
     * Aggregate order totals from items
     */
    protected function calculateItemTotals(Collection $items): array
    {
        $baseSubtotal = $items->sum('subtotal');
        $baseSubtotalTax = $items->sum('subtotal_tax');
        $baseSubtotalIncMargin = $items->sum('subtotal_inc_margin');
        $baseSubtotalIncMarginTax = $items->sum('subtotal_inc_margin_tax');

        return [
            'base_subtotal_before_discount' => $baseSubtotal,
            'base_subtotal' => $baseSubtotal,
            'base_subtotal_tax' => $baseSubtotalTax,
            'base_subtotal_inc_margin_before_discount' => $baseSubtotalIncMargin,
            'base_subtotal_inc_margin' => $baseSubtotalIncMargin,
            'base_subtotal_inc_margin_tax' => $baseSubtotalIncMarginTax,
        ];
    }

    protected function shouldSplitOrders(int $vendorId): bool
    {
        return VendorMeta::where([
            'vendor_id' => $vendorId,
            'key' => 'allow_order_splitting',
        ])->value('value') === '1';
    }

    protected function generateOrderNumber(): string
    {
        // Ensure the sequence exists (without lock first) to avoid gap locking issues
        OrderSequence::firstOrCreate(
            ['prefix' => 'AIO'],
            ['current_value' => 0]
        );

        $sequence = OrderSequence::where('prefix', 'AIO')->lockForUpdate()->first();
        $sequence->current_value++;
        $sequence->save();

        return $sequence->prefix.'-'.str_pad($sequence->current_value, 7, '0', STR_PAD_LEFT);
    }

    protected function createOrderItemDesigns(SalesOrderItem $orderItem, int $templateId): void
    {
        $layers = VendorDesignLayer::with('catalogTemplateLayer')
            ->where('vendor_design_template_id', $templateId)
            ->get();

        $variant = CatalogProduct::with('attributes')->find($orderItem->variant_id);
        $variantOptionIds = $variant ? $variant->attributes->pluck('attribute_value')->filter()->values()->all() : [];

        $allImages = VendorDesignLayerImage::where('template_id', $templateId)
            ->get();

        foreach ($layers as $layer) {
            $candidates = $allImages->where('layer_id', $layer->catalog_design_template_layer_id);

            // 1. Try exact variant match
            $layerImage = $candidates->firstWhere('variant_id', $orderItem->variant_id);

            // 2. Try color match
            if (! $layerImage && ! empty($variantOptionIds)) {
                $layerImage = $candidates->whereIn('color_id', $variantOptionIds)->first();
            }

            SalesOrderItemDesign::create([
                'order_item_id' => $orderItem->id,
                'layer_id' => $layer->catalog_design_template_layer_id,
                'layer_name' => $layer->catalogTemplateLayer->layer_name ?? 'Unknown Layer',
                'base_image' => $layer->image_path,
                'preview_image' => $layerImage?->image,
                'design_data' => [
                    'scale_x' => $layer->scale_x,
                    'scale_y' => $layer->scale_y,
                    'rotation_angle' => $layer->rotation_angle,
                    'position_top' => $layer->position_top,
                    'position_left' => $layer->position_left,
                    'width' => $layer->width,
                    'height' => $layer->height,
                    'technique_id' => $layer->technique_id,
                ],
            ]);
        }
    }

    protected function createOrderItemOptions(SalesOrderItem $orderItem, CatalogProduct $variant): void
    {
        foreach ($variant->attributes as $attribute) {
            // Only save if there is a value or option
            if (! $attribute->attribute_value && ! $attribute->value) {
                continue;
            }

            SalesOrderItemOption::create([
                'order_item_id' => $orderItem->id,
                'option_id' => (int) $attribute->attribute_value, // References catalog_attribute_options.option_id
                'option_name' => $attribute->attribute->description->name ?? $attribute->attribute->attribute_code,
                'option_value' => $attribute->option->key ?? $attribute->value ?? '',
            ]);
        }
    }

    protected function createOrderAddress(SalesOrder $order, CartAddress $address): void
    {
        foreach (['shipping', 'billing'] as $type) {
            SalesOrderAddress::create([
                'order_id' => $order->id,
                'address_type' => $type,
                'first_name' => $address->first_name,
                'last_name' => $address->last_name,
                'email' => $address->email,
                'phone' => $address->phone,
                'address_line_1' => $address->address_line_1,
                'address_line_2' => $address->address_line_2,
                'city' => $address->city,
                'state_id' => $address->state_id,
                'state' => $address->state,
                'postal_code' => $address->postal_code,
                'country_id' => $address->country_id,
                'country' => $address->country,
            ]);
        }
    }

    public function calculateTaxForCart(Cart $cart, float $amount): array
    {
        $countryId = $cart->address?->country_id;
        $stateCode = null;
        $postalCode = $cart->address?->postal_code;

        if ($cart->address && $cart->address->state_id) {
            $state = State::find($cart->address->state_id);
            $stateCode = $state?->iso2;
        }

        if (! $countryId) {
            return [
                'tax_name' => null,
                'tax_rate' => 0,
                'tax_amount' => 0,
            ];
        }

        $taxData = $this->taxResolverService->calculate($amount, $countryId, $stateCode, $postalCode);

        return [
            'tax_name' => $taxData['tax'],
            'tax_rate' => $taxData['rate'],
            'tax_amount' => $taxData['amount'],
        ];
    }

    public function getCartTaxRate(Cart $cart): float
    {
        $countryId = $cart->address?->country_id;
        $stateCode = null;
        $postalCode = $cart->address?->postal_code;

        if ($cart->address && $cart->address->state_id) {
            $state = State::find($cart->address->state_id);
            $stateCode = $state?->iso2;
        }

        if (! $countryId) {
            return 0.0;
        }

        // Calculate with dummy amount to get rate
        $taxData = $this->taxResolverService->calculate(100, $countryId, $stateCode, $postalCode);

        return (float) ($taxData['rate'] ?? 0);
    }

    public function getTaxDetailsFromResolver(int $countryId, ?string $stateCode = null, ?string $postalCode = null): array
    {
        // Calculate with dummy amount (100) to get rate/name
        $taxData = $this->taxResolverService->calculate(100, $countryId, $stateCode, $postalCode);

        return [
            'tax_name' => $taxData['tax'] ?? null,
            'tax_rate' => $taxData['rate'] ?? 0,
        ];
    }

    /**
     * Resolve branding pricing data for an order item.
     *
     * @param  float  $marginDecimal  Per-item margin as decimal (e.g. 0.20 for 20%)
     */
    protected function resolveItemBrandingData(CartItem $item, float $marginDecimal, ?PackagingLabel $factoryPackaging, ?HangTag $factoryHangTag): ?array
    {
        if (! $item->packaging_label_id && ! $item->hang_tag_id) {
            return null;
        }

        $packagingLabel = $item->packagingLabel;
        $hangTag = $item->hangTag;

        if (! $packagingLabel && ! $hangTag) {
            return null;
        }

        $qty = (int) $item->qty;
        $brandingData = [
            'packaging_label_id' => null,
            'hang_tag_id' => null,
            'applied_packaging_label_id' => $item->packaging_label_id,
            'applied_hang_tag_id' => $item->hang_tag_id,
            'packaging_base_price' => 0,
            'packaging_margin_price' => 0,
            'hang_tag_base_price' => 0,
            'hang_tag_margin_price' => 0,
            'qty' => $qty,
            'packaging_total' => 0,
            'hang_tag_total' => 0,
        ];

        if ($packagingLabel && $factoryPackaging && $factoryPackaging->is_active) {
            $cost = 0;
            if ($packagingLabel->image) {
                $cost += (float) $factoryPackaging->front_price;
            }
            if ($packagingLabel->image_back) {
                $cost += (float) $factoryPackaging->back_price;
            }

            $total = $marginDecimal >= 1 ? $cost : $cost / (1 - $marginDecimal);
            $margin = $total - $cost;

            $brandingData['packaging_label_id'] = $factoryPackaging->id;
            $brandingData['packaging_base_price'] = $cost;
            $brandingData['packaging_margin_price'] = $margin;
            $brandingData['packaging_total'] = $total * $qty;
        }

        if ($hangTag && $factoryHangTag && $factoryHangTag->is_active) {
            $cost = 0;
            if ($hangTag->image) {
                $cost += (float) $factoryHangTag->front_price;
            }
            if ($hangTag->image_back) {
                $cost += (float) $factoryHangTag->back_price;
            }

            $total = $marginDecimal >= 1 ? $cost : $cost / (1 - $marginDecimal);
            $margin = $total - $cost;

            $brandingData['hang_tag_id'] = $factoryHangTag->id;
            $brandingData['hang_tag_base_price'] = $cost;
            $brandingData['hang_tag_margin_price'] = $margin;
            $brandingData['hang_tag_total'] = $total * $qty;
        }

        // Always save if factory items were active, even if price was 0
        if (!$brandingData['packaging_label_id'] && !$brandingData['hang_tag_id']) {
            return null;
        }

        Log::info('Branding resolved for order item', [
            'packaging_label_id' => $item->packaging_label_id,
            'hang_tag_id' => $item->hang_tag_id,
        ]);

        return $brandingData;
    }
}
