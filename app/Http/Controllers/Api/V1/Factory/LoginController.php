<?php

namespace App\Http\Controllers\Api\V1\Factory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Factory\LoginRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class LoginController extends Controller
{
    protected int $maxAttempts = 6;

    protected int $decaySeconds = 60;

    /**
     * Handle factory login.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $this->ensureIsNotRateLimited($request);

        // Invalidate existing token if already logged in
        if (Auth::guard('factory')->check()) {
            try {
                Auth::guard('factory')->invalidate(true);
            } catch (\Throwable $e) {
                // Ignore errors during invalidation
            }
        }

        $credentials = $request->only('email', 'password');

        // Set token TTL based on remember option
        $ttl = $request->boolean('remember') ? config('jwt.remember_ttl', 43200) : config('jwt.ttl', 60);
        Auth::guard('factory')->factory()->setTTL((int) $ttl);

        // Attempt authentication
        if (! $token = Auth::guard('factory')->attempt($credentials)) {
            RateLimiter::hit($this->throttleKey($request), $this->decaySeconds);

            return response()->json([
                'success' => false,
                'message' => __('Invalid credentials.'),
            ], Response::HTTP_UNAUTHORIZED);
        }

        $factory = Auth::guard('factory')->user();

        // Check if email is verified
        if (! $factory->email_verified_at) {
            Auth::guard('factory')->logout();
            RateLimiter::hit($this->throttleKey($request), $this->decaySeconds);

            return response()->json([
                'success' => false,
                'message' => __('Email address not verified. Please verify your email before logging in.'),
            ], Response::HTTP_FORBIDDEN);
        }

        // Check account status (if active)
        if ($factory->account_status === 0) {
            Auth::guard('factory')->logout();
            RateLimiter::hit($this->throttleKey($request), $this->decaySeconds);

            return response()->json([
                'success' => false,
                'message' => __('Your account is inactive. Please contact support.'),
            ], Response::HTTP_FORBIDDEN);
        }

        // Update last login timestamp
        $factory->forceFill(['last_login' => now()])->saveQuietly();

        // Clear rate limiter
        RateLimiter::clear($this->throttleKey($request));

        return $this->respondWithToken($token, $factory);
    }

    /**
     * Handle factory logout.
     */
    public function logout(): JsonResponse
    {
        try {
            Auth::guard('factory')->logout();

            return response()->json([
                'success' => true,
                'message' => __('Successfully logged out.'),
            ], Response::HTTP_OK);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => true,
                'message' => __('Successfully logged out.'),
            ], Response::HTTP_OK);
        }
    }

    /**
     * Return token response with factory data.
     */
    protected function respondWithToken(string $token, $factory = null): JsonResponse
    {
        return response()->json([
            'success' => true,
            'token' => $token,
            'factory' => $factory ? $this->factoryPayload($factory) : null,
            'message' => __('Successfully logged in.'),
        ], Response::HTTP_OK);
    }

    /**
     * Prepare factory payload for response.
     */
    protected function factoryPayload($factory): array
    {
        return [
            'name' => trim(($factory->first_name ?? '').' '.($factory->last_name ?? '')),
            'email' => $factory->email,
        ];
    }

    /**
     * Rate limiter helpers
     */
    protected function throttleKey(Request $request): string
    {
        return Str::transliterate(Str::lower($request->input('email')).'|'.$request->ip());
    }

    protected function ensureIsNotRateLimited(Request $request): void
    {
        $key = $this->throttleKey($request);
        if (RateLimiter::tooManyAttempts($key, $this->maxAttempts)) {
            $seconds = RateLimiter::availableIn($key);
            throw new \Illuminate\Http\Exceptions\HttpResponseException(
                response()->json([
                    'success' => false,
                    'message' => __('Too many login attempts. Please try again in :seconds seconds.', ['seconds' => $seconds]),
                ], Response::HTTP_TOO_MANY_REQUESTS)
            );
        }
    }
}
