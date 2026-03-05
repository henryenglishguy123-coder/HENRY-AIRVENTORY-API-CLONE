<?php

namespace App\Services\Customer;

use App\Models\Customer\Vendor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CustomerResolverService
{
    public function resolve(Request $request)
    {
        if (Auth::guard('customer')->check()) {
            return Auth::guard('customer')->user();
        }
        if (Auth::guard('admin_api')->check()) {
            $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
                'customer_id' => 'required|integer|exists:vendors,id',
            ]);

            if ($validator->fails()) {
                abort(Response::HTTP_UNPROCESSABLE_ENTITY, $validator->errors()->first());
            }

            return Vendor::findOrFail($request->input('customer_id'));
        }
        abort(Response::HTTP_UNAUTHORIZED, 'Unauthenticated');
    }
}
