<?php

namespace App\Http\Controllers\Api\V1\Customer\Address;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Customer\Address\ShippingAddressRequest;
use App\Models\Customer\VendorShippingAddress;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ShippingAddressController extends Controller
{
    public function store(ShippingAddressRequest $request): JsonResponse
    {
        $customer = Auth::guard('customer')->user();
        $address = VendorShippingAddress::updateOrCreate(
            [
                'vendor_id' => $customer->id,
            ],
            [
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'email' => $request->email,
                'phone' => $request->phone,
                'address' => $request->address,
                'country_id' => $request->country_id,
                'state_id' => $request->state_id,
                'city' => $request->city,
                'postal_code' => $request->postal_code,
                'is_default' => true, // always default (only one)
                'status' => true,
            ]
        );

        return response()->json([
            'success' => true,
            'message' => __('Shipping address saved successfully.'),
            'data' => $address->makeHidden([
                'vendor_id',
                'created_at',
                'updated_at',
                'deleted_at',
            ]),
        ], Response::HTTP_OK);
    }

    public function show(): JsonResponse
    {
        $customer = Auth::guard('customer')->user();

        $address = VendorShippingAddress::where('vendor_id', $customer->id)->first();

        if (! $address) {
            return response()->json([
                'success' => false,
                'message' => __('Shipping address not found.'),
                'data' => null,
            ], Response::HTTP_NOT_FOUND);
        }

        return response()->json([
            'success' => true,
            'message' => __('Shipping address fetched successfully.'),
            'data' => $address->makeHidden([
                'vendor_id',
                'created_at',
                'updated_at',
                'deleted_at',
            ]),
        ], Response::HTTP_OK);
    }
}
