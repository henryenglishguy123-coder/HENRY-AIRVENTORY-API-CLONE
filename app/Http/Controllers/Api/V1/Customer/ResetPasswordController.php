<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Requests\Api\V1\Customer\ResetPasswordRequest;
use App\Mail\Customer\CustomerPasswordChangedMail;
use App\Models\Customer\Vendor;
use App\Models\Customer\VendorMeta;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class ResetPasswordController extends SigninController
{
    public function reset(ResetPasswordRequest $request): JsonResponse
    {
        $throttleKey = 'password-reset|'.Str::lower($request->input('email'));
        if (RateLimiter::tooManyAttempts($throttleKey, 10)) {
            return response()->json([
                'message' => __('Too many attempts. Please try again later.'),
            ], Response::HTTP_TOO_MANY_REQUESTS);
        }

        DB::beginTransaction();
        try {
            $email = $request->input('email');
            $plainToken = $request->input('token');
            $customer = Vendor::where('email', $email)->first();
            if (! $customer) {
                RateLimiter::hit($throttleKey, 60);

                return response()->json([
                    'message' => __('No customer found with that email.'),
                ], Response::HTTP_NOT_FOUND);
            }
            $storedHashed = $customer->metaValue('password_reset_token');
            $expiresAt = $customer->metaValue('password_reset_expires_at');
            if (! $storedHashed || ! $expiresAt) {
                RateLimiter::hit($throttleKey, 60);

                return response()->json([
                    'message' => __('Invalid or expired reset token.'),
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            $incomingHash = hash('sha256', (string) $plainToken);
            if (! hash_equals((string) $storedHashed, $incomingHash)) {
                RateLimiter::hit($throttleKey, 60);

                return response()->json([
                    'message' => __('Invalid reset token.'),
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            $expiresCarbon = Carbon::parse($expiresAt);
            if (Carbon::now()->greaterThan($expiresCarbon)) {
                VendorMeta::where('vendor_id', $customer->id)
                    ->whereIn('key', ['password_reset_token', 'password_reset_expires_at'])
                    ->delete();
                RateLimiter::hit($throttleKey, 60);

                return response()->json([
                    'message' => __('Reset token has expired.'),
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            $customer->password = $request->input('password');
            if ($customer->isFillable('password_changed_at') || property_exists($customer, 'password_changed_at')) {
                $customer->password_changed_at = Carbon::now();
            }
            $customer->save();
            Mail::to($customer->email)->queue(
                new CustomerPasswordChangedMail($customer, request()->ip())
            );
            VendorMeta::where('vendor_id', $customer->id)
                ->whereIn('key', ['password_reset_token', 'password_reset_expires_at'])
                ->delete();
            DB::commit();
            RateLimiter::clear($throttleKey);
            event(new PasswordReset($customer));

            return response()->json([
                'message' => __('Your password has been updated successfully. You may now sign in using your new credentials.'),
            ], Response::HTTP_OK);
        } catch (\Throwable $e) {
            DB::rollBack();
            if (config('app.debug')) {
                return response()->json(['message' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
            }

            return response()->json([
                'message' => __('Unable to reset password. Please try again later.'),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
