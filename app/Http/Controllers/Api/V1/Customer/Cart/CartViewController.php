<?php

namespace App\Http\Controllers\Api\V1\Customer\Cart;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Customer\CartResource;
use App\Services\Customer\Cart\CartRoutingService;
use App\Services\Customer\Cart\CartService;
use App\Services\Customer\Cart\CartTotalsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CartViewController extends Controller
{
    public function __construct(
        protected CartRoutingService $routingService,
        protected CartTotalsService $totalsService
    ) {}

    public function view(CartService $cartService): JsonResponse
    {
        $customer = Auth::guard('customer')->user();

        if (! $customer) {
            return $this->unauthorizedResponse();
        }

        $cart = $cartService->findActiveCart($customer->id);

        if (! $cart) {
            return $this->cartNotFoundResponse();
        }

        $relations = [
            'items.options',
            'items.designImages',
            'items.template.product.children',
            'items.variant',
            'totals',
            'discount',
            'errors',
            'address',
        ];

        // Initial load
        $cart->load($relations);

        // Apply routing logic (may update cart/items)
        $this->routingService->processCartRouting($cart);

        // Always recalculate totals to ensure shipping and other totals are up-to-date
        $this->totalsService->recalculate($cart);

        // Reload fresh state after routing and recalculation
        $cart->refresh()->load($relations);

        return response()->json([
            'success' => true,
            'data' => new CartResource($cart),
        ], Response::HTTP_OK);
    }

    protected function unauthorizedResponse(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'data' => null,
            'message' => __('Unauthenticated.'),
        ], Response::HTTP_UNAUTHORIZED);
    }

    protected function cartNotFoundResponse(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'data' => null,
            'message' => __('Cart not found.'),
        ], Response::HTTP_NOT_FOUND);
    }
}
