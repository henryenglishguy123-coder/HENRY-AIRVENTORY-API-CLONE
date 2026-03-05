<?php

namespace App\Services\Customer\Cart;

use App\Models\Customer\Cart\Cart;
use App\Models\Marketing\Discount\DiscountCoupon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CartDiscountService
{
    public function __construct(
        protected CartTotalsService $cartTotalsService
    ) {}

    /**
     * Apply a coupon to the cart.
     */
    public function applyCoupon(Cart $cart, string $code): array
    {
        return DB::transaction(function () use ($cart, $code) {
            $coupon = DiscountCoupon::where('code', $code)->lockForUpdate()->first();

            if (! $coupon) {
                throw ValidationException::withMessages([
                    'code' => ['Invalid coupon code.'],
                ]);
            }

            // Validate Coupon
            $this->validateCoupon($coupon, $cart);

            // Calculate Discount
            $discountAmount = $this->calculateDiscount($coupon, $cart);

            // Handle existing discount replacement (Release usage of old coupon)
            $cart->load('discount');
            if ($cart->discount) {
                if ($cart->discount->code) {
                    DiscountCoupon::where('code', $cart->discount->code)->decrement('uses_count');
                }
                $cart->discount->delete();
            }

            // Create new discount
            $cart->discount()->create([
                'source' => 'coupon',
                'code' => $coupon->code,
                'amount' => $discountAmount,
            ]);

            // Increment usage count (Reserve usage for new coupon)
            $coupon->increment('uses_count');

            // Recalculate totals
            $this->cartTotalsService->recalculate($cart);

            return [
                'code' => $coupon->code,
                'amount' => $discountAmount,
            ];
        });
    }

    public function refreshDiscount(Cart $cart): void
    {
        $cart->load('discount');
        if (! $cart->discount) {
            return;
        }
        $code = $cart->discount->code;
        if (! $code) {
            return;
        }
        $coupon = DiscountCoupon::where('code', $code)->first();
        if (! $coupon) {
            $this->removeDiscount($cart);

            return;
        }
        $cart->unsetRelation('totals');

        try {
            $this->validateCoupon($coupon, $cart, true);
            $newAmount = $this->calculateDiscount($coupon, $cart);
            if ($cart->discount->amount != $newAmount) {
                $cart->discount->amount = $newAmount;
                $cart->discount->save();
            }
        } catch (ValidationException $e) {
            $this->removeDiscount($cart);
        }
        $this->cartTotalsService->recalculate($cart);
    }

    /**
     * Remove discount from cart.
     */
    public function removeDiscount(Cart $cart): void
    {
        DB::transaction(function () use ($cart) {
            $cart->load('discount');
            if ($cart->discount) {
                if ($cart->discount->code) {
                    DiscountCoupon::where('code', $cart->discount->code)->decrement('uses_count');
                }
                $cart->discount->delete();
            }
            $this->cartTotalsService->recalculate($cart);
        });
    }

    /**
     * Get authoritative cart subtotal from totals relation.
     */
    protected function getCartSubtotal(Cart $cart): float
    {

        if ($cart->relationLoaded('totals')) {
            return (float) ($cart->totals?->subtotal ?? 0);
        }

        return (float) $cart->items()->sum('line_total');
    }

    /**
     * Validate the coupon against the cart.
     */
    protected function validateCoupon(DiscountCoupon $coupon, Cart $cart, bool $isRefresh = false): void
    {
        $now = now();

        if ($coupon->status !== 'Active') {
            throw ValidationException::withMessages(['code' => ['Coupon is inactive.']]);
        }

        if ($coupon->start_date && $coupon->start_date->gt($now)) {
            throw ValidationException::withMessages(['code' => ['Coupon is not yet active.']]);
        }

        if ($coupon->end_date && $coupon->end_date->lt($now)) {
            throw ValidationException::withMessages(['code' => ['Coupon has expired.']]);
        }
        if (! $isRefresh) {
            if ($coupon->max_uses > 0 && $coupon->uses_count >= $coupon->max_uses) {
                throw ValidationException::withMessages(['code' => ['Coupon usage limit reached.']]);
            }
        }

        // Percentage range validation (0-100)
        if ($coupon->amount_type === 'Percentage') {
            if ($coupon->amount_value < 0 || $coupon->amount_value > 100) {
                throw ValidationException::withMessages(['code' => ['Invalid discount percentage.']]);
            }
        }

        // Check minimum order amount
        $subtotal = $this->getCartSubtotal($cart);

        if ($coupon->min_price > 0 && $subtotal < $coupon->min_price) {
            throw ValidationException::withMessages(['code' => ["Minimum order amount of {$coupon->min_price} required."]]);
        }
    }

    /**
     * Calculate discount amount.
     */
    protected function calculateDiscount(DiscountCoupon $coupon, Cart $cart): float
    {
        $subtotal = $this->getCartSubtotal($cart);
        $amount = 0;
        if ($coupon->amount_type === 'Percentage') {
            $amount = ($subtotal * $coupon->amount_value) / 100;
        } else {
            $amount = $coupon->amount_value;
        }

        return min((float) $amount, (float) $subtotal);
    }
}
