<?php

namespace App\Services\Customer\Cart\Actions;

use App\Models\Catalog\Attribute\CatalogAttributeOption;
use App\Models\Catalog\Product\CatalogProduct;
use App\Models\Customer\Cart\CartItem;
use App\Models\Customer\Designer\VendorDesignTemplate;
use App\Services\Customer\Cart\CartDiscountService;
use App\Services\Customer\Cart\CartPricingService;
use App\Services\Customer\Cart\CartService;
use App\Services\Customer\Cart\CartTotalsService;
use App\Services\Customer\Cart\CartVariantResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AddTemplateToCartAction
{
    public function __construct(
        protected CartService $cartService,
        protected CartVariantResolver $variantResolver,
        protected CartPricingService $pricingService,
        protected CartTotalsService $totalsService,
        protected CartDiscountService $discountService
    ) {}

    public function execute($customer, array $data)
    {
        $qty = isset($data['qty']) ? (int) $data['qty'] : 1;

        if ($qty < 0) {
            throw ValidationException::withMessages([
                'qty' => __('The quantity cannot be negative.'),
            ]);
        }

        $data['qty'] = $qty;

        return DB::transaction(function () use ($customer, $data) {

            $cart = $this->cartService->getActiveCart($customer->id);
            $template = VendorDesignTemplate::findOrFail($data['template_id']);
            $template->loadMissing('storeBranding');
            $product = CatalogProduct::findOrFail($data['product_id']);

            $selectedOptions = collect($data['selected_options'] ?? [])
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->sort()
                ->values();

            if ($selectedOptions->isEmpty()) {
                throw ValidationException::withMessages([
                    'selected_options' => [__('You must select at least one product option.')],
                ]);
            }

            $dbOptions = CatalogAttributeOption::with('attribute')
                ->whereIn('option_id', $selectedOptions)
                ->get();

            if ($dbOptions->count() !== $selectedOptions->count()) {
                throw ValidationException::withMessages([
                    'selected_options' => __('Invalid product options selected.'),
                ]);
            }

            $variant = $this->variantResolver->resolve($product, $selectedOptions);

            if (! $variant) {
                throw ValidationException::withMessages([
                    'product_id' => __('Selected product option combination is not available.'),
                ]);
            }

            $unitPrice = $this->pricingService->resolveUnitPrice($variant, $template);

            if ($unitPrice <= 0) {
                throw ValidationException::withMessages([
                    'product_id' => __('Pricing for the selected product is not available.'),
                ]);
            }

            $fulfillmentFactoryId = null;

            if ($data['qty'] > 0) {
                $fulfillmentFactoryId = $this->pricingService->getFulfillmentFactoryId($variant, $template);

                if (! $fulfillmentFactoryId) {
                    throw ValidationException::withMessages([
                        'product_id' => __('This product is currently unavailable for fulfillment.'),
                    ]);
                }
            }

            $item = null;
            if (isset($data['cart_item_id'])) {
                $item = CartItem::where('id', $data['cart_item_id'])
                    ->where('cart_id', $cart->id)
                    ->first();
            }

            if (! $item) {
                $item = CartItem::where([
                    'cart_id' => $cart->id,
                    'product_id' => $product->id,
                    'variant_id' => $variant->id,
                    'template_id' => $template->id,
                ])->first();
            }

            /**
             * if qty = 0 and item exists → remove
             */
            if ($item && $data['qty'] === 0) {
                $item->options()->delete();
                $item->delete();

                $cart->unsetRelation('items');
                $this->discountService->refreshDiscount($item->cart);
                $this->totalsService->recalculate($cart);

                return $cart->refresh()->load([
                    'items.options',
                    'items.designImages',
                    'totals',
                    'errors',
                    'address',
                    'discount',
                    'items.template.product.children',
                ]);
            }

            /**
             * Update existing item
             */
            if ($item) {
                $item->update([
                    'product_id' => $product->id,
                    'variant_id' => $variant->id,
                    'template_id' => $template->id,
                    'packaging_label_id' => $template->store_branding?->packaging_label_id,
                    'hang_tag_id' => $template->store_branding?->hang_tag_id,
                    'qty' => $data['qty'],
                    'unit_price' => $unitPrice,
                    'sku' => $variant->sku,
                    'product_title' => $variant->info?->name,
                    'line_total' => bcmul($unitPrice, $data['qty'], 2),
                    'fulfillment_factory_id' => $fulfillmentFactoryId,
                ]);

                // Update options for existing item (delete old, create new)
                $item->options()->delete();
                $item->options()->createMany(
                    $dbOptions->map(fn ($option) => [
                        'option_id' => $option->option_id,
                        'option_code' => $option->attribute->attribute_code,
                        'option_value' => $option->key,
                    ])->values()->all()
                );
            }
            /**
             * Create new item (only if qty > 0)
             */ elseif ($data['qty'] > 0) {
                $item = CartItem::create([
                    'cart_id' => $cart->id,
                    'product_id' => $product->id,
                    'variant_id' => $variant->id,
                    'template_id' => $template->id,
                    'packaging_label_id' => $template->store_branding?->packaging_label_id,
                    'hang_tag_id' => $template->store_branding?->hang_tag_id,
                    'qty' => $data['qty'],
                    'unit_price' => $unitPrice,
                    'sku' => $variant->sku,
                    'product_title' => $variant->info?->name,
                    'line_total' => bcmul($unitPrice, $data['qty'], 2),
                    'fulfillment_factory_id' => $fulfillmentFactoryId,
                ]);

                $item->options()->createMany(
                    $dbOptions->map(fn ($option) => [
                        'option_id' => $option->option_id,
                        'option_code' => $option->attribute->attribute_code,
                        'option_value' => $option->key,
                    ])->values()->all()
                );
            }

            $cart->unsetRelation('items');
            $this->discountService->refreshDiscount($item->cart);

            $this->totalsService->recalculate($cart);

            return $cart->refresh()->load([
                'items.options',
                'totals',
                'errors',
                'address',
                'discount',
                'items.template.product.children',
            ]);
        });
    }
}
