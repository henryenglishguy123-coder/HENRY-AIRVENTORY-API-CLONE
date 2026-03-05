<?php

namespace App\Http\Controllers\Api\V1\Customer\Cart;

use App\Http\Controllers\Api\V1\Customer\Account\AccountController;
use App\Http\Controllers\Controller;
use App\Models\Customer\Cart\Cart;
use App\Services\Customer\Cart\CartDiscountService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CartDiscountController extends Controller
{
    public function __construct(
        protected CartDiscountService $cartDiscountService
    ) {}

    /**
     * Apply a coupon to the cart.
     */
    public function apply(Request $request)
    {
        $request->validate([
            'code' => 'required|string',
        ]);

        $user = app(AccountController::class)->resolveCustomer($request);

        // Find the user's active cart
        $cart = Cart::where('vendor_id', $user->id)
            ->where('status', 'active')
            ->first();

        if (! $cart) {
            return response()->json(['message' => __('Cart not found')], 404);
        }

        $code = $request->input('code');

        try {
            $result = $this->cartDiscountService->applyCoupon($cart, $code);

            return response()->json([
                'message' => __('Coupon applied successfully.'),
                'discount' => $result,
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => __('Invalid coupon.'),
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => __('Failed to apply coupon.'),
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Remove discount from cart.
     */
    public function remove(Request $request)
    {
        $user = app(AccountController::class)->resolveCustomer($request);

        $cart = Cart::where('vendor_id', $user->id)
            ->where('status', 'active')
            ->first();

        if (! $cart) {
            return response()->json(['message' => __('Cart not found')], 404);
        }

        $this->cartDiscountService->removeDiscount($cart);

        return response()->json(['message' => __('Discount removed successfully.')]);
    }
}
