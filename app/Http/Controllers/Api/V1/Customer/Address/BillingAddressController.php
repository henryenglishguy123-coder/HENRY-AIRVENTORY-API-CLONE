<?php

namespace App\Http\Controllers\Api\V1\Customer\Address;

use App\Http\Controllers\Api\V1\Customer\Account\AccountController;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Customer\Address\BillingAddressRequest;
use App\Models\Customer\VendorBillingAddress;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BillingAddressController extends Controller
{
    public function store(BillingAddressRequest $request): JsonResponse
    {
        $customer = app(AccountController::class)->resolveCustomer($request);
        $address = VendorBillingAddress::updateOrCreate(
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
                'company_name' => $request->company_name,
                'tax_number' => $request->tax_number,
                'is_default' => true,
                'status' => true,
            ]
        );

        return response()->json([
            'success' => true,
            'message' => __('Billing address saved successfully.'),
            'data' => $address->makeHidden([
                'vendor_id',
                'created_at',
                'updated_at',
                'deleted_at',
            ]),
        ], Response::HTTP_OK);
    }

    public function show(Request $request): JsonResponse
    {

        $customer = app(AccountController::class)->resolveCustomer($request);

        $address = VendorBillingAddress::where('vendor_id', $customer->id)->first();

        if (! $address) {
            return response()->json([
                'success' => false,
                'message' => __('Billing address not found.'),
                'data' => null,
            ], Response::HTTP_NOT_FOUND);
        }

        return response()->json([
            'success' => true,
            'message' => __('Billing address fetched successfully.'),
            'data' => $address->makeHidden([
                'vendor_id',
                'created_at',
                'updated_at',
                'deleted_at',
            ]),
        ], Response::HTTP_OK);
    }
}
