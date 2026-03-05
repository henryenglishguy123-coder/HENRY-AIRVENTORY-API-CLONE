<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class AdminJWTAuthController extends Controller
{
    protected function resolveAuthToken(Request $request): ?string
    {
        $authorization = $request->headers->get('Authorization', '');
        if (is_string($authorization) && stripos($authorization, 'bearer ') === 0) {
            $token = trim(substr($authorization, 7));
            if ($token !== '') {
                return $token;
            }
        }

        $cookieToken = $request->cookie('jwt_token');
        if (is_string($cookieToken) && trim($cookieToken) !== '') {
            return trim($cookieToken);
        }

        $cookieHeader = $request->headers->get('Cookie', '');
        if (is_string($cookieHeader) && $cookieHeader !== '') {
            foreach (explode(';', $cookieHeader) as $part) {
                if (strpos($part, 'jwt_token=') !== false) {
                    $value = trim(substr($part, strpos($part, '=') + 1));
                    if ($value !== '') {
                        return $value;
                    }
                }
            }
        }

        return null;
    }

    protected function clearAuthCookies($response)
    {
        return $response->withCookie(cookie()->forget('jwt_token'));
    }

    /**
     * Apply admin-specific JWT TTL and refresh TTL for this request.
     */
    protected function applyAdminJwtConfig(?int $ttl = null): array
    {
        $defaultTtl = (int) env('ADMIN_JWT_TTL', (int) env('JWT_TTL', 60));
        $rememberTtl = (int) config('jwt.remember_ttl', (int) env('JWT_REMEMBER_TTL', 43200));
        $refreshTtl = (int) env('ADMIN_JWT_REFRESH_TTL', (int) env('JWT_REFRESH_TTL', 20160));

        $effectiveTtl = $ttl ?? $defaultTtl;

        // Apply per-request overrides safely
        config(['jwt.ttl' => $effectiveTtl]);
        config(['jwt.refresh_ttl' => $refreshTtl]);

        return [
            'ttl' => $effectiveTtl,
            'remember_ttl' => $rememberTtl,
            'refresh_ttl' => $refreshTtl,
        ];
    }

    /**
     * Get the authenticated admin's details.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function me()
    {
        $admin = Auth::guard('admin_api')->user();

        return response()->json([
            'success' => true,
            'admin' => [
                'id' => $admin->id,
                'name' => $admin->name,
                'email' => $admin->email,
                'username' => $admin->username,
                'user_type' => $admin->user_type,
                'mobile' => $admin->mobile,
            ],
        ], Response::HTTP_OK);
    }

    /**
     * Logout the admin and invalidate all related sessions/tokens.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout(Request $request)
    {
        $guards = ['admin_api', 'admin', 'web'];
        $results = [];
        $errors = [];

        foreach ($guards as $guard) {
            try {
                if (Auth::guard($guard)->check()) {
                    Auth::guard($guard)->logout();
                    $results[$guard] = 'logged_out';
                } else {
                    $results[$guard] = 'not_logged_in';
                }
            } catch (\Throwable $e) {
                $results[$guard] = 'error';
                $errors[$guard] = $e->getMessage();
                Log::error('Admin logout guard error', [
                    'guard' => $guard,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        $sessionCleared = false;
        try {
            if (method_exists($request, 'hasSession') ? $request->hasSession() : true) {
                $request->session()->invalidate();
                $request->session()->regenerateToken();
                $sessionCleared = true;
            }
        } catch (\Throwable $e) {
            $errors['session'] = $e->getMessage();
            Log::error('Admin logout session error', [
                'message' => $e->getMessage(),
            ]);
        }

        $stillAuthenticated = [];
        foreach ($guards as $guard) {
            try {
                if (Auth::guard($guard)->check()) {
                    $stillAuthenticated[] = $guard;
                }
            } catch (\Throwable $e) {
                $stillAuthenticated[] = $guard;
            }
        }

        if ($errors || $stillAuthenticated) {
            Log::warning('Admin logout partial failure', [
                'results' => $results,
                'errors' => $errors,
                'still_authenticated' => $stillAuthenticated,
                'session_cleared' => $sessionCleared,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Logout encountered issues',
                'details' => [
                    'guards' => $results,
                    'still_authenticated' => $stillAuthenticated,
                ],
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        Log::info('Admin logout successful', [
            'results' => $results,
            'session_cleared' => $sessionCleared,
        ]);

        $response = response()->json([
            'success' => true,
            'message' => 'Successfully logged out',
        ], Response::HTTP_OK);

        return $this->clearAuthCookies($response);
    }

    /**
     * Refresh the JWT token.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function refresh(Request $request)
    {
        try {
            // Optional TTL override on refresh
            $ttlParam = $request->input('ttl');
            $remember = filter_var($request->input('remember', false), FILTER_VALIDATE_BOOL);
            $config = $this->applyAdminJwtConfig();

            $ttl = $config['ttl'];
            if (is_numeric($ttlParam)) {
                $ttl = (int) $ttlParam;
            } elseif ($remember) {
                $ttl = (int) $config['remember_ttl'];
            }

            Auth::guard('admin_api')->factory()->setTTL($ttl);
            $token = Auth::guard('admin_api')->refresh();

            return response()->json([
                'success' => true,
                'message' => 'Token refreshed successfully',
                'token' => $token,
                'expires_in' => $ttl * 60,
                'refresh_expires_in' => $config['refresh_ttl'] * 60,
            ], Response::HTTP_OK);
        } catch (\Tymon\JWTAuth\Exceptions\TokenInvalidException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid token',
            ], Response::HTTP_UNAUTHORIZED);
        } catch (\Tymon\JWTAuth\Exceptions\TokenExpiredException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Token has expired',
            ], Response::HTTP_UNAUTHORIZED);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Could not refresh token',
            ], Response::HTTP_UNAUTHORIZED);
        }
    }

    /**
     * Mint a JWT token for a session-authenticated admin.
     * This allows admins logged in via web session to get a JWT for API use.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function mintToken(Request $request)
    {
        // Check if admin is authenticated via session
        if (! Auth::guard('admin')->check()) {
            return response()->json([
                'success' => false,
                'message' => 'You must be logged in via the admin panel to mint a token',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $admin = Auth::guard('admin')->user();

        // Determine TTL
        $ttlParam = $request->input('ttl');
        $remember = filter_var($request->input('remember', false), FILTER_VALIDATE_BOOL);
        $config = $this->applyAdminJwtConfig();

        $ttl = $config['ttl'];
        if (is_numeric($ttlParam)) {
            $ttl = (int) $ttlParam;
        } elseif ($remember) {
            $ttl = (int) $config['remember_ttl'];
        }

        // Generate JWT token for the authenticated admin with configured TTL
        Auth::guard('admin_api')->factory()->setTTL($ttl);
        $token = Auth::guard('admin_api')->login($admin);

        return response()->json([
            'success' => true,
            'message' => 'JWT token generated successfully',
            'token' => $token,
            'expires_in' => $ttl * 60,
            'refresh_expires_in' => $config['refresh_ttl'] * 60,
        ], Response::HTTP_OK);
    }
}
