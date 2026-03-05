<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthCustomerOrAdmin
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next)
    {
        if (
            Auth::guard('customer')->check() ||
            Auth::guard('admin_api')->check()
        ) {
            return $next($request);
        }

        return response()->json([
            'success' => false,
            'message' => __('Unauthorized'),
        ], 401);
    }
}
