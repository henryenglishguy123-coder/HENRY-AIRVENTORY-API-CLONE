<?php

namespace App\Services\Customer\Cart;

use App\Models\Customer\Cart\Cart;
use App\Models\Factory\FactoryShippingRate;
use App\Models\Location\Country;
use Illuminate\Support\Facades\Log;

class CartShippingService
{
    /**
     * Calculate total shipping cost for the cart.
     * Logic:
     * 1. Group items by fulfillment factory.
     * 2. For each factory group:
     *    a. Determine applicable shipping rate based on destination country and total quantity (min_qty check).
     *    b. Calculate total weight of items in the group.
     *    c. Shipping cost = Total Weight * Rate Price.
     * 3. Sum shipping costs from all groups.
     */
    public function calculateShipping(Cart $cart): float
    {
        if ($cart->items->isEmpty()) {
            return 0.0;
        }

        if (! $cart->address || ! $cart->address->country_id) {
            return 0.0;
        }
        if ($cart->items->isNotEmpty() && ! $cart->items->first()->relationLoaded('variant')) {
            $cart->items->load('variant');
        }
        $country = Country::find($cart->address->country_id);
        if (! $country) {
            throw new \RuntimeException(sprintf(
                'Country not found for ID %s (Cart ID: %s). Cannot calculate shipping.',
                $cart->address->country_id,
                $cart->id
            ));
        }
        $countryCode = $country->iso2;
        $itemsByFactory = $cart->items->groupBy('fulfillment_factory_id');
        $totalShipping = 0.0;
        foreach ($itemsByFactory as $factoryId => $items) {
            if (empty($factoryId)) {
                Log::warning('Cart items found without assigned fulfillment factory during shipping calculation.', [
                    'cart_id' => $cart->id,
                    'item_ids' => $items->pluck('id')->toArray(),
                ]);

                continue;
            }
            $totalQty = $items->sum('qty');
            $totalWeight = $items->sum(function ($item) use ($cart) {
                $weight = $item->variant?->weight ?? null;
                if ($weight === null) {
                    Log::warning('Product variant (or weight) missing during shipping calculation.', [
                        'cart_id' => $cart->id,
                        'item_id' => $item->id,
                        'variant_id' => $item->variant_id ?? 'N/A',
                    ]);
                    $weight = 0;
                }

                return (float) $weight * $item->qty;
            });
            $rate = FactoryShippingRate::where('factory_id', $factoryId)
                ->where('country_code', $countryCode)
                ->where('min_qty', '<=', $totalQty)
                ->orderBy('min_qty', 'desc') // highest qualifying slab
                ->orderBy('price', 'asc')    // cheapest if same slab exists
                ->first();
            if ($rate) {
                $shippingCost = $totalWeight * $rate->price;
                $totalShipping += $shippingCost;
            } else {
                throw new \RuntimeException(sprintf(
                    'No shipping rate found for factory ID %s (Country: %s, Qty: %s). Please configure shipping rates.',
                    $factoryId,
                    $countryCode,
                    $totalQty
                ));
            }
        }

        return $totalShipping;
    }
}
