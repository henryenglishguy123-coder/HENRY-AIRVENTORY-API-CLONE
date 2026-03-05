<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Customer\ForgotPasswordRequest;
use App\Http\Requests\Api\V1\Customer\VerifyResetTokenRequest;
use App\Mail\Customer\CustomerResetPasswordMail;
use App\Models\Customer\Vendor;
use App\Support\Customers\CustomerMeta;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class ForgotPasswordController extends Controller
{
    protected int $maxAttempts = 5;

    protected int $decaySeconds = 60;

    public function sendResetLink(ForgotPasswordRequest $request): JsonResponse
    {
        $this->ensureIsNotRateLimited($request);
        DB::beginTransaction();
        try {
            $customer = Vendor::where('email', $request->email)->first();
            $plainToken = Str::random(64);
            $customer->setMetaValue('password_reset_token', hash('sha256', $plainToken));
            $customer->setMetaValue('password_reset_expires_at', Carbon::now()->addMinutes(60));
            $customerPanelUrl = rtrim(config('app.customer_panel_url'), '/');
            $resetUrl = "{$customerPanelUrl}/auth/reset-password?token={$plainToken}&email={$customer->email}";
            Mail::to($customer->email)->queue(
                new CustomerResetPasswordMail($customer, $resetUrl)
            );
            DB::commit();
            RateLimiter::clear($this->throttleKey($request));

            return response()->json([
                'message' => __('We have emailed your password reset link.'),
            ], Response::HTTP_OK);
        } catch (\Throwable $e) {
            DB::rollBack();
            if (config('app.debug')) {
                return response()->json(['message' => $e->getMessage()], 500);
            }

            return response()->json([
                'message' => __('Unable to send reset link. Please try again later.'),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    protected function ensureIsNotRateLimited(ForgotPasswordRequest $request): void
    {
        $key = $this->throttleKey($request);
        if (RateLimiter::tooManyAttempts($key, $this->maxAttempts)) {
            $seconds = RateLimiter::availableIn($key);
            throw ValidationException::withMessages([
                'email' => [__('Too many requests. Please try again in :seconds seconds.', ['seconds' => $seconds])],
            ])->status(Response::HTTP_TOO_MANY_REQUESTS);
        }
        RateLimiter::hit($key, $this->decaySeconds);
    }

    protected function throttleKey(ForgotPasswordRequest $request): string
    {
        $email = (string) Str::lower($request->input('email', ''));
        $ip = (string) $request->ip();

        return 'forgot-password|'.md5($email.'|'.$ip);
    }

    public function verifyToken(VerifyResetTokenRequest $request): JsonResponse
    {
        $data = $request->validated();
        $customer = Vendor::where('email', $data['email'])->first();

        if (! $customer) {
            return response()->json(['success' => false, 'message' => __('Invalid token or email.')], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $storedHash = CustomerMeta::get($customer->id, 'password_reset_token');
        $expiresAtRaw = CustomerMeta::get($customer->id, 'password_reset_expires_at');

        if (! $storedHash || ! $expiresAtRaw) {
            return response()->json(['success' => false, 'message' => __('Invalid or expired reset token.')], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $expiresAt = $expiresAtRaw instanceof Carbon ? $expiresAtRaw : Carbon::parse($expiresAtRaw);
        if ($expiresAt->isPast()) {
            return response()->json(['success' => false, 'message' => __('Reset token has expired.')], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $providedHash = hash('sha256', $data['token']);
        if (! hash_equals($storedHash, $providedHash)) {
            return response()->json(['success' => false, 'message' => __('Invalid reset token.')], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return response()->json(['success' => true, 'message' => __('Reset token is valid.')], Response::HTTP_OK);
    }
}
