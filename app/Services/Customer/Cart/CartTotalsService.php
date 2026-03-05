<?php

namespace App\Services\Customer\Cart;

use App\Models\Customer\Cart\Cart;
use App\Models\Customer\Cart\CartTotal;
use App\Models\Location\State;
use App\Services\Tax\TaxResolverService;

class CartTotalsService
{
    public function __construct(
        protected CartShippingService $shippingService,
        protected TaxResolverService $taxResolverService
    ) {}

    public function recalculate(Cart $cart): void
    {
        $subtotal = $cart->items->sum('line_total');
        $shippingAmount = 0.0;

        try {
            $shippingAmount = $this->shippingService->calculateShipping($cart);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Shipping calculation failed during recalculate', [
                'cart_id' => $cart->id,
                'error' => $e->getMessage(),
                'class' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);

            \Illuminate\Support\Facades\DB::transaction(function () use ($cart, $e) {
                \App\Models\Customer\Cart\CartError::create([
                    'cart_id' => $cart->id,
                    'error_code' => 'SHIPPING_ERROR',
                    'error_message' => $e->getMessage(),
                ]);
                $cart->status = 'hold';
                $cart->save();
            });
        }

        // Resolve Address Info
        $countryId = $cart->address?->country_id;
        $stateCode = null;
        $postalCode = $cart->address?->postal_code;

        if ($cart->address && $cart->address->state_id) {
            $state = State::find($cart->address->state_id);
            $stateCode = $state?->iso2;
        }

        // Discount (authoritative amount from existing cart discount)
        $cart->load('discount');
        $discountAmount = (float) ($cart->discount->amount ?? 0);

        // Calculate Shipping Tax
        $shippingTax = 0;
        if ($countryId) {
            $taxData = $this->taxResolverService->calculate(
                $shippingAmount,
                $countryId,
                $stateCode,
                $postalCode
            );
            $shippingTax = $taxData['amount'] ?? 0;
        }

        // Calculate Subtotal Tax (on discounted subtotal)
        $subtotalTax = 0;
        $taxableSubtotal = max(0, $subtotal - $discountAmount);

        if ($countryId) {
            $taxData = $this->taxResolverService->calculate(
                $taxableSubtotal,
                $countryId,
                $stateCode,
                $postalCode
            );
            $subtotalTax = $taxData['amount'] ?? 0;
        }

        $shippingTotal = $shippingAmount + $shippingTax;
        $taxTotal = $subtotalTax + $shippingTax;

        CartTotal::updateOrCreate(
            ['cart_id' => $cart->id],
            [
                'subtotal' => $subtotal,
                'subtotal_tax' => $subtotalTax,
                'shipping_amount' => $shippingAmount,
                'shipping_tax' => $shippingTax,
                'shipping_total' => $shippingTotal,
                'tax_total' => $taxTotal,
                'discount_total' => $discountAmount,
                'grand_total' => max(0, ($subtotal - $discountAmount) + $subtotalTax + $shippingTotal),
                'calculated_at' => now(),
            ]
        );
    }
}
