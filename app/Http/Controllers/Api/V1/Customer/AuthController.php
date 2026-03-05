<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Models\Customer\Vendor;
use App\Models\Customer\VendorMeta;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AuthController extends Controller
{
    /**
     * Verify email via signed link (no auth required).
     * URL: GET /api/v1/email/?{token}
     */
    public function verifyEmail(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
        ]);
        $token = $request->input('token');
        $hashed = hash('sha256', $token);
        try {
            DB::beginTransaction();
            $meta = VendorMeta::where('key', 'email_verification_token')
                ->where('value', $hashed)
                ->lockForUpdate()
                ->first();
            if (! $meta) {
                return response()->json(['message' => __('Invalid token')], 400);
            }
            $customer = Vendor::find($meta->vendor_id);
            if (! $customer) {
                return response()->json(['message' => __('Invalid token owner')], 400);
            }
            $expiresAt = Carbon::parse($customer->metaValue('email_verification_expires_at'));
            if (now()->greaterThan($expiresAt)) {
                return response()->json(['message' => __('Token expired')], 400);
            }
            if ($customer->email_verified_at) {
                return response()->json(['message' => __('Email already verified.')], 200);
            }
            $customer->update(['email_verified_at' => now()]);
            VendorMeta::where('vendor_id', $customer->id)->whereIn('key', ['email_verification_token', 'email_verification_expires_at'])->delete();
            event(new Verified($customer));
            DB::commit();

            return response()->json(['message' => __('Your email has been verified successfully.')], 200);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'message' => __('Email verification failed, please try again later.'),
            ], 500);
        }
    }
}
