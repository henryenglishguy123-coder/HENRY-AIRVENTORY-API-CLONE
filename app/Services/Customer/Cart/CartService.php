<?php

namespace App\Services\Customer\Cart;

use App\Models\Customer\Cart\Cart;
use Illuminate\Support\Facades\DB;

class CartService
{
    public function getActiveCart(int $vendorId): Cart
    {
        return Cart::firstOrCreate(
            [
                'vendor_id' => $vendorId,
                'status' => 'active',
            ]
        );
    }

    public function findActiveCart(int $vendorId): ?Cart
    {
        return Cart::where('vendor_id', $vendorId)
            ->where('status', 'active')
            ->first();
    }

    public function getActiveCartForUpdate(int $vendorId): Cart
    {
        return DB::transaction(function () use ($vendorId) {
            try {
                $cart = Cart::firstOrCreate([
                    'vendor_id' => $vendorId,
                    'status' => 'active',
                ]);
            } catch (\Illuminate\Database\QueryException $e) {
                // Retry once if unique constraint violation occurs (race condition)
                if ($e->getCode() === '23000') {
                    // Cart created by another request in the meantime, fetch it
                } else {
                    throw $e;
                }
            }

            // Now lock and return
            return Cart::where('vendor_id', $vendorId)
                ->where('status', 'active')
                ->lockForUpdate()
                ->firstOrFail();
        });
    }
}
