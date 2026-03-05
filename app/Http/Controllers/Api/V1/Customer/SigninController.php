<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Customer\SigninRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class SigninController extends Controller
{
    protected int $maxAttempts = 6;

    protected int $decaySeconds = 60;

    public function signin(SigninRequest $request): JsonResponse
    {
        $this->ensureIsNotRateLimited($request);
        if (Auth::guard('customer')->check()) {
            try {
                Auth::guard('customer')->invalidate(true);
            } catch (\Throwable $e) {
            }
        }
        $credentials = $request->only('email', 'password');
        $ttl = $request->boolean('remember') ? config('jwt.remember_ttl', 43200) : config('jwt.ttl', 60);
        Auth::guard('customer')->factory()->setTTL((int) $ttl);
        if (! $token = Auth::guard('customer')->attempt($credentials)) {
            RateLimiter::hit($this->throttleKey($request), $this->decaySeconds);

            return response()->json([
                'message' => __('Invalid credentials.'),
            ], Response::HTTP_UNAUTHORIZED);
        }
        RateLimiter::clear($this->throttleKey($request));
        $customer = Auth::guard('customer')->user();
        $customer->forceFill(['last_login' => now()])->saveQuietly();

        return $this->respondWithToken($token, $customer);
    }

    public function signout(): JsonResponse
    {
        try {
            Auth::guard('customer')->logout();

            return response()->json(['message' => __('Successfully logged out.')], Response::HTTP_OK);
        } catch (\Throwable $e) {
            return response()->json(['message' => __('Successfully logged out.')], Response::HTTP_OK);
        }
    }

    protected function respondWithToken(string $token, $customer = null)
    {
        return response()->json([
            'token' => $token,
            'customer' => $customer ? $this->customerPayload($customer) : null,
        ], Response::HTTP_OK);
    }

    protected function customerPayload($user): array
    {
        return [
            'name' => trim(($user->first_name ?? '').' '.($user->last_name ?? '')),
            'email' => $user->email,
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
            abort(response()->json([
                'message' => __('Too many login attempts. Please try again in :seconds seconds.', ['seconds' => $seconds]),
            ], Response::HTTP_TOO_MANY_REQUESTS));
        }
    }
}
