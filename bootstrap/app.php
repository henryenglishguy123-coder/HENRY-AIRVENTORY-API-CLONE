<?php

use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Auth\Middleware\RedirectIfAuthenticated;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        api: [
            __DIR__.'/../routes/api.php',
            __DIR__.'/../routes/shopify.php',
            __DIR__.'/../routes/woocommerce.php',
        ],
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        RedirectIfAuthenticated::redirectUsing(function (Request $request) {
            if (Route::has('admin.dashboard')) {
                return route('admin.dashboard');
            }
        });
        Authenticate::redirectUsing(function (Request $request) {
            if (Route::has('admin.login')) {
                return route('admin.login');
            }
        });
        $middleware->alias([
            'auth.customer_or_admin' => \App\Http\Middleware\AuthCustomerOrAdmin::class,
            'auth.any' => \App\Http\Middleware\AuthAnyUser::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
