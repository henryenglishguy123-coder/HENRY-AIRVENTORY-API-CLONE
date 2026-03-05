<?php

namespace App\Http\Controllers\Api\V1\Factory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Factory\ForgotPasswordRequest;
use App\Mail\Factory\FactoryResetPasswordMail;
use App\Models\Factory\Factory;
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

    /**
     * Send password reset link to factory email.
     */
    public function sendResetLink(ForgotPasswordRequest $request): JsonResponse
    {
        $this->ensureIsNotRateLimited($request);

        DB::beginTransaction();
        try {
            $factory = Factory::where('email', $request->email)->first();

            if (! $factory) {
                DB::rollBack();

                return response()->json([
                    'success' => false,
                    'data' => null,
                    'message' => __('Unable to send reset link. Please try again later.'),
                ], Response::HTTP_INTERNAL_SERVER_ERROR);
            }

            // Generate reset token
            $plainToken = Str::random(64);
            $factory->setMetaValue('password_reset_token', hash('sha256', $plainToken));
            $factory->setMetaValue('password_reset_expires_at', Carbon::now()->addMinutes(60));

            // Build reset URL
            $factoryPanelUrl = rtrim(config('app.factory_panel_url', config('app.url')), '/');
            $resetUrl = "{$factoryPanelUrl}/auth/reset-password?token={$plainToken}&email={$factory->email}";

            // Send email with reset link
            Mail::to($factory->email)->queue(
                new FactoryResetPasswordMail($factory, $resetUrl)
            );

            DB::commit();
            RateLimiter::clear($this->throttleKey($request));

            return response()->json([
                'success' => true,
                'data' => null,
                'message' => __('We have emailed your password reset link.'),
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
                'message' => __('Unable to send reset link. Please try again later.'),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Ensure request is not rate limited.
     */
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

    /**
     * Generate rate limiter throttle key.
     */
    protected function throttleKey(ForgotPasswordRequest $request): string
    {
        $email = (string) Str::lower($request->input('email', ''));
        $ip = (string) $request->ip();

        return 'factory-forgot-password|'.md5($email.'|'.$ip);
    }
}
