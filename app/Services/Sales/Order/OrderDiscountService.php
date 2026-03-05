<?php

namespace App\Services\Sales\Order;

use App\Models\Marketing\Discount\DiscountCoupon;
use Illuminate\Validation\ValidationException;

class OrderDiscountService
{
    /**
     * Validate and calculate discount for a given amount.
     *
     * @throws ValidationException
     */
    public function checkCoupon(string $code, float $subtotal): array
    {
        $coupon = DiscountCoupon::where('code', $code)->first();

        if (! $coupon) {
            throw ValidationException::withMessages([
                'code' => ['Invalid coupon code.'],
            ]);
        }

        // Validate Coupon
        $this->validateCoupon($coupon, $subtotal);

        // Calculate Discount using integer cents
        $subtotalCents = (int) round($subtotal * 100);
        $discountAmountCents = $this->calculateDiscount($coupon, $subtotalCents);
        $discountAmount = $discountAmountCents / 100;

        return [
            'code' => $coupon->code,
            'amount' => $discountAmount,
            'coupon_id' => $coupon->id,
            'type' => $coupon->amount_type,
            'value' => $coupon->amount_value,
        ];
    }

    /**
     * Validate the coupon logic against a generic amount.
     */
    public function validateCoupon(DiscountCoupon $coupon, float $subtotal): void
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

        // Check usage limit
        if ($coupon->max_uses > 0 && $coupon->uses_count >= $coupon->max_uses) {
            throw ValidationException::withMessages(['code' => ['Coupon usage limit reached.']]);
        }

        // Percentage range validation (0-100)
        if ($coupon->amount_type === 'Percentage') {
            if ($coupon->amount_value < 0 || $coupon->amount_value > 100) {
                throw ValidationException::withMessages(['code' => ['Invalid discount percentage.']]);
            }
        }

        // Check minimum order amount
        if ($coupon->min_price > 0 && $subtotal < $coupon->min_price) {
            throw ValidationException::withMessages(['code' => ["Minimum order amount of {$coupon->min_price} required."]]);
        }
    }

    /**
     * Atomically apply the coupon (increment usage).
     *
     * @throws ValidationException
     */
    public function apply(string $code): void
    {
        $now = now();
        $updated = DiscountCoupon::where('code', $code)
            ->where('status', 'Active')
            ->where(function ($q) use ($now) {
                $q->whereNull('start_date')->orWhere('start_date', '<=', $now);
            })
            ->where(function ($q) use ($now) {
                $q->whereNull('end_date')->orWhere('end_date', '>=', $now);
            })
            ->where(function ($query) {
                $query->where('max_uses', 0)
                    ->orWhereColumn('uses_count', '<', 'max_uses');
            })
            ->increment('uses_count');

        if (! $updated) {
            throw ValidationException::withMessages(['code' => ['Coupon is invalid, expired, or usage limit reached.']]);
        }
    }

    /**
     * Calculate discount amount.
     */
    protected function calculateDiscount(DiscountCoupon $coupon, int $subtotalCents): int
    {
        $amount = 0;
        if ($coupon->amount_type === 'Percentage') {
            $amount = (int) round(($subtotalCents * $coupon->amount_value) / 100);
        } elseif ($coupon->amount_type === 'Fixed') {
            // Assuming amount_value is in dollars, convert to cents
            $amount = (int) round($coupon->amount_value * 100);
        } else {
            throw new \InvalidArgumentException("Invalid coupon amount type: {$coupon->amount_type}");
        }

        return min($amount, $subtotalCents);
    }
}
