<?php

namespace App\Http\Controllers\Api\V1\Factory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Factory\ResetPasswordRequest;
use App\Mail\Factory\FactoryPasswordChangedMail;
use App\Models\Factory\Factory;
use App\Models\Factory\FactoryMetas;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class ResetPasswordController extends Controller
{
    /**
     * Reset the factory's password.
     */
    public function reset(ResetPasswordRequest $request): JsonResponse
    {
        $throttleKey = 'factory-password-reset|'.Str::lower($request->input('email'));
        if (RateLimiter::tooManyAttempts($throttleKey, 10)) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => __('Too many attempts. Please try again later.'),
            ], Response::HTTP_TOO_MANY_REQUESTS);
        }

        DB::beginTransaction();
        try {
            $email = $request->input('email');
            $plainToken = $request->input('token');
            $factory = Factory::where('email', $email)->first();

            if (! $factory) {
                RateLimiter::hit($throttleKey, 60);

                return response()->json([
                    'success' => false,
                    'data' => null,
                    'message' => __('No factory found with that email.'),
                ], Response::HTTP_NOT_FOUND);
            }

            // Get stored token hash and expiration
            $storedHashed = $factory->metaValue('password_reset_token');
            $expiresAt = $factory->metaValue('password_reset_expires_at');

            if (! $storedHashed || ! $expiresAt) {
                RateLimiter::hit($throttleKey, 60);

                return response()->json([
                    'success' => false,
                    'data' => null,
                    'message' => __('Invalid or expired reset token.'),
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            // Verify token hash
            $plainTokenString = (string) $plainToken;
            if (empty($plainTokenString)) {
                RateLimiter::hit($throttleKey, 60);

                return response()->json([
                    'success' => false,
                    'data' => null,
                    'message' => __('Invalid reset token.'),
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $incomingHash = hash('sha256', $plainTokenString);
            if (! hash_equals((string) $storedHashed, $incomingHash)) {
                RateLimiter::hit($throttleKey, 60);

                return response()->json([
                    'success' => false,
                    'data' => null,
                    'message' => __('Invalid reset token.'),
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            // Check if token has expired
            $expiresCarbon = Carbon::parse($expiresAt);
            if (Carbon::now()->greaterThan($expiresCarbon)) {
                FactoryMetas::where('factory_id', $factory->id)
                    ->whereIn('key', ['password_reset_token', 'password_reset_expires_at'])
                    ->delete();
                RateLimiter::hit($throttleKey, 60);

                return response()->json([
                    'success' => false,
                    'data' => null,
                    'message' => __('Reset token has expired.'),
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            // Update password
            $factory->password = $request->input('password');
            $factory->save();

            // Send password changed notification email
            Mail::to($factory->email)->queue(
                new FactoryPasswordChangedMail($factory, request()->ip())
            );

            // Clear reset token from meta
            FactoryMetas::where('factory_id', $factory->id)
                ->whereIn('key', ['password_reset_token', 'password_reset_expires_at'])
                ->delete();

            DB::commit();
            RateLimiter::clear($throttleKey);

            event(new PasswordReset($factory));

            return response()->json([
                'success' => true,
                'data' => null,
                'message' => __('Your password has been updated successfully. You may now sign in using your new credentials.'),
            ], Response::HTTP_OK);
        } catch (\Throwable $e) {
            DB::rollBack();
            if (config('app.debug')) {
                return response()->json([
                    'success' => false,
                    'data' => null,
                    'message' => $e->getMessage(),
                ], Response::HTTP_INTERNAL_SERVER_ERROR);
            }

            return response()->json([
                'success' => false,
                'data' => null,
                'message' => __('Unable to reset password. Please try again later.'),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
