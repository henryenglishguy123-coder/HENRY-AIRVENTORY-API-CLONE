<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AuthAnyUser
{
    /**
     * Handle an incoming request.
     * Checks if user is authenticated via any of: customer, factory, or admin guards.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next)
    {
        if (
            Auth::guard('customer')->check() ||
            Auth::guard('factory')->check() ||
            Auth::guard('admin_api')->check()
        ) {
            return $next($request);
        }

        return response()->json([
            'message' => __('Unauthenticated.'),
        ], Response::HTTP_UNAUTHORIZED);
    }
}
