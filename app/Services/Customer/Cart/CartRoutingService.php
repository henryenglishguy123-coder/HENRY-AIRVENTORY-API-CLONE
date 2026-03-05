<?php

namespace App\Services\Customer\Cart;

use App\Models\Customer\Cart\Cart;
use App\Models\Customer\Cart\CartError;
use App\Models\Customer\Cart\CartItem;
use App\Models\Factory\FactorySalesRouting;
use App\Models\Location\State;
use App\Services\Tax\TaxResolverService;
use App\Support\Customers\CustomerMeta;
use Illuminate\Support\Facades\Log;

class CartRoutingService
{
    public function __construct(
        protected InventoryService $inventoryService,
        protected CartPricingService $pricingService,
        protected CartTotalsService $totalsService,
        protected TaxResolverService $taxResolver
    ) {}

    /**
     * Process cart items and assign the correct fulfillment factory based on account settings and stock.
     */
    public function processCartRouting(Cart $cart): void
    {
        // 1. Clear previous errors and reset status
        CartError::where('cart_id', $cart->id)->delete();

        if ($cart->status === 'hold') {
            $cart->status = 'active';
            $cart->save();
        }

        // 2. Address check
        if (! $cart->address || ! $cart->address->country_id) {
            // Cannot route without a shipping address. This is common during early checkout
            // steps where the customer hasn't provided details yet.
            return;
        }

        // 3. Retrieve Account Settings
        $fulfillmentType = CustomerMeta::get($cart->vendor_id, 'fulfillment_type', 'auto');
        $allowSplitOrders = (bool) CustomerMeta::get($cart->vendor_id, 'allow_split_orders', false);

        $hasChanges = false;

        // 4. Fulfillment Logic
        if ($fulfillmentType === 'manual') {
            $hasChanges = $this->processManualRouting($cart);
        } else {
            $hasChanges = $this->processAutoRouting($cart, $allowSplitOrders);
        }

        // 5. Apply Tax Calculation
        if ($this->applyItemTaxes($cart)) {
            $hasChanges = true;
        }

        // 6. Recalculate Totals if routing or taxes changed
        if ($hasChanges) {
            $this->totalsService->recalculate($cart);
        }

        // 7. Final check for errors and place on hold if any critical issues were logged
        if (CartError::where('cart_id', $cart->id)->exists()) {
            $this->holdCart($cart);
        }
    }

    protected function applyItemTaxes(Cart $cart): bool
    {
        if (! $cart->address) {
            return false;
        }

        $address = $cart->address;
        $stateCode = null;

        if ($address->state_id) {
            $state = State::find($address->state_id);
            $stateCode = $state?->iso2;
        }

        $hasUpdates = false;

        foreach ($cart->items as $item) {
            $lineTotal = (float) $item->line_total;
            $lineTotalUpdated = false;
            if ($lineTotal == 0 && $item->qty > 0) {
                $lineTotal = (float) bcmul((string) $item->unit_price, (string) $item->qty, 2);
                $item->line_total = $lineTotal;
                $lineTotalUpdated = true;
            }

            try {
                $taxData = $this->taxResolver->calculate(
                    $lineTotal,
                    $address->country_id,
                    $stateCode,
                    $address->postal_code
                );

                if (
                    $lineTotalUpdated ||
                    abs((float) $item->tax_rate - (float) $taxData['rate']) > 0.0001 ||
                    abs((float) $item->tax_amount - (float) $taxData['amount']) > 0.0001
                ) {
                    $item->tax_rate = $taxData['rate'];
                    $item->tax_amount = $taxData['amount'];
                    $item->save();
                    $hasUpdates = true;
                }
            } catch (\Throwable $e) {
                Log::error('Tax calculation failed', [
                    'item_id' => $item->id,
                    'error' => $e->getMessage(),
                ]);
                $this->logError($cart, $item->sku, null, 'TAX_ERROR', 'Tax calculation failed.');
            }
        }

        return $hasUpdates;
    }

    protected function processManualRouting(Cart $cart): bool
    {
        // Identify selected factory from items (assuming all items should match)
        $selectedFactoryId = $this->determineManualFactory($cart);

        if (! $selectedFactoryId) {
            if ($cart->items->count() > 0) {
                // Check if it's mixed or just missing
                $factories = $cart->items->pluck('fulfillment_factory_id')->unique()->filter()->values();
                if ($factories->count() > 1) {
                    $this->logError($cart, null, null, 'MIXED_FACTORIES', 'Multiple factories selected in manual mode.');
                } else {
                    $this->logError($cart, null, null, 'FACTORY_NOT_SELECTED', 'No valid factory selected for manual fulfillment.');
                }

                return false;
            }

            return false;
        }

        $hasChanges = false;

        foreach ($cart->items as $item) {
            // Validate Stock
            $variant = $item->variant;
            if (! $variant) {
                continue;
            }

            if (! $this->inventoryService->hasStockInFactory($variant, $selectedFactoryId)) {
                // Out of stock -> HOLD
                $this->logError($cart, $item->sku ?? $variant->sku, $selectedFactoryId, 'STOCK_UNAVAILABLE', 'Selected factory out of stock.');
            }

            // Ensure item is assigned to this factory (it should be, but let's enforce/repair)
            if ($item->fulfillment_factory_id !== $selectedFactoryId) {
                if ($this->updateItemFactory($item, $selectedFactoryId)) {
                    $hasChanges = true;
                }
            }
        }

        return $hasChanges;
    }

    protected function determineManualFactory(Cart $cart): ?int
    {
        $factoryId = null;
        foreach ($cart->items as $item) {
            if ($item->fulfillment_factory_id) {
                if ($factoryId === null) {
                    $factoryId = $item->fulfillment_factory_id;
                } elseif ($factoryId !== $item->fulfillment_factory_id) {
                    // Mixed factories in manual mode -> Inconsistent
                    return null;
                }
            }
        }

        return $factoryId;
    }

    protected function processAutoRouting(Cart $cart, bool $allowSplitOrders): bool
    {
        $countryId = $cart->address->country_id;
        $routingFactoryIds = FactorySalesRouting::query()
            ->where('country_id', $countryId)
            ->orderBy('priority', 'asc')
            ->pluck('factory_id')
            ->toArray();

        if (empty($routingFactoryIds)) {
            $countryName = $cart->address->country ?? "ID: {$countryId}";
            $this->logError($cart, null, null, 'NO_ROUTING_RULES', "No shipping routes configured for {$countryName}.");

            return false;
        }

        if (! $allowSplitOrders) {
            return $this->processAutoSingleFactory($cart, $routingFactoryIds);
        } else {
            return $this->processAutoSplitFactory($cart, $routingFactoryIds);
        }
    }

    protected function processAutoSingleFactory(Cart $cart, array $routingFactoryIds): bool
    {
        // Iterate routing rules to find ONE factory that can fulfill ALL items
        $bestFactoryId = null;

        foreach ($routingFactoryIds as $factoryId) {
            $allInStock = true;
            foreach ($cart->items as $item) {
                $variant = $item->variant;
                if (! $variant) {
                    continue;
                }

                if (! $this->inventoryService->hasStockInFactory($variant, $factoryId)) {
                    $allInStock = false;
                    break;
                }
            }

            if ($allInStock) {
                $bestFactoryId = $factoryId;
                break; // Found the best priority factory
            }
        }

        if ($bestFactoryId) {
            // Assign all items to this factory
            $hasChanges = false;
            foreach ($cart->items as $item) {
                if ($item->fulfillment_factory_id !== $bestFactoryId) {
                    if ($this->updateItemFactory($item, $bestFactoryId)) {
                        $hasChanges = true;
                    }
                }
            }

            return $hasChanges;
        } else {
            // No single factory has all items
            $this->logError($cart, null, null, 'SPLIT_REQUIRED', 'Order cannot be fulfilled by a single factory.');

            return false;
        }
    }

    protected function processAutoSplitFactory(Cart $cart, array $routingFactoryIds): bool
    {
        $hasChanges = false;

        foreach ($cart->items as $item) {
            $variant = $item->variant;
            if (! $variant) {
                continue;
            }

            // Find best factory for this item
            $bestFactoryId = null;
            foreach ($routingFactoryIds as $factoryId) {
                if ($this->inventoryService->hasStockInFactory($variant, $factoryId)) {
                    $bestFactoryId = $factoryId;
                    break; // Priority order
                }
            }

            if ($bestFactoryId) {
                if ($item->fulfillment_factory_id !== $bestFactoryId) {
                    if ($this->updateItemFactory($item, $bestFactoryId)) {
                        $hasChanges = true;
                    }
                }
            } else {
                // No factory has stock for this item
                $this->logError($cart, $item->sku ?? $variant->sku, null, 'STOCK_UNAVAILABLE', 'Item out of stock in all routed factories.');
            }
        }

        return $hasChanges;
    }

    protected function updateItemFactory(CartItem $item, int $targetFactoryId): bool
    {
        if ($item->fulfillment_factory_id === $targetFactoryId) {
            return false;
        }

        if (! $item->variant) {
            $this->logError($item->cart, $item->sku, $targetFactoryId, 'VARIANT_NOT_FOUND', 'Product variant not found.');

            return false;
        }

        // Recalculate price for the new target factory
        if ($item->template) {
            try {
                $newUnitPrice = $this->pricingService->calculatePriceForFactory(
                    $item->variant,
                    $item->template,
                    $targetFactoryId
                );

                // Update item with new factory and price
                $item->fulfillment_factory_id = $targetFactoryId;

                // Use BCMath for precise currency calculations
                $unitPriceStr = (string) $newUnitPrice;
                $item->unit_price = $unitPriceStr;
                $item->line_total = bcmul($unitPriceStr, (string) $item->qty, 2);

                $item->save();

                return true;

            } catch (\Exception $e) {
                Log::error('Price recalculation failed during factory update', [
                    'item_id' => $item->id,
                    'sku' => $item->sku,
                    'factory_id' => $targetFactoryId,
                    'error' => $e->getMessage(),
                ]);

                $fallbackSku = $item->sku ?? $item->variant?->sku;
                $this->logError($item->cart, $fallbackSku, $targetFactoryId, 'PRICING_ERROR', "Failed to calculate price for SKU {$fallbackSku} at factory ID {$targetFactoryId}.");

                return false;
            }
        } else {
            // Simple items without templates just get reassigned
            $item->fulfillment_factory_id = $targetFactoryId;
            $item->save();

            return true;
        }
    }

    protected function holdCart(Cart $cart): void
    {
        $cart->status = 'hold';
        $cart->save();

        Log::info("Cart {$cart->id} placed on HOLD due to routing errors.");
    }

    protected function logError(Cart $cart, ?string $sku, ?int $factoryId, string $code, string $message): void
    {
        Log::warning("Cart Error [{$code}]: {$message}", [
            'cart_id' => $cart->id,
            'sku' => $sku,
            'factory_id' => $factoryId,
        ]);

        CartError::create([
            'cart_id' => $cart->id,
            'sku' => $sku,
            'factory_id' => $factoryId,
            'error_code' => $code,
            'error_message' => $message,
        ]);
    }
}
