<?php

namespace App\Http\Controllers\Api\V1\Customer\Cart;

use App\Http\Controllers\Api\V1\Customer\Account\AccountController;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Customer\Cart\CartAddressRequest;
use App\Models\Customer\Cart\CartAddress;
use App\Models\Location\Country;
use App\Models\Location\State;
use App\Services\Customer\Cart\CartService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CartAddressController extends Controller
{
    public function store(
        CartAddressRequest $request,
        CartService $cartService
    ): JsonResponse {
        $customer = app(AccountController::class)->resolveCustomer($request);
        $cart = $cartService->getActiveCart($customer->id);
        $country = Country::find($request->country_id);
        $state = $request->state_id
            ? State::find($request->state_id)
            : null;
        $addressData = array_merge(
            $request->only([
                'first_name',
                'last_name',
                'email',
                'phone',
                'address_line_1',
                'address_line_2',
                'city',
                'postal_code',
                'country_id',
                'state_id',
            ]),
            [
                'country' => $country?->name,
                'state' => $state?->name,
            ]
        );

        $address = CartAddress::updateOrCreate(
            ['cart_id' => $cart->id],
            $addressData
        );

        return response()->json([
            'success' => true,
            'message' => __('Cart address saved successfully.'),
            'data' => $address->makeHidden([
                'cart_id',
                'created_at',
                'updated_at',
            ]),
        ], Response::HTTP_OK);
    }

    public function show(CartService $cartService, Request $request): JsonResponse
    {
        $customer = app(AccountController::class)->resolveCustomer($request);
        $cart = $cartService->getActiveCart($customer->id);

        if (! $cart->address) {
            return response()->json([
                'success' => false,
                'message' => __('Cart address not found.'),
                'data' => null,
            ], Response::HTTP_NOT_FOUND);
        }

        return response()->json([
            'success' => true,
            'message' => __('Cart address fetched successfully.'),
            'data' => $cart->address->makeHidden([
                'cart_id',
                'created_at',
                'updated_at',
            ]),
        ], Response::HTTP_OK);
    }
}
