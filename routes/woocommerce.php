<?php

use App\Http\Controllers\Api\V1\Webhook\WooCommerceWebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| WooCommerce Integration Routes
|--------------------------------------------------------------------------
|
| - Webhook receivers
| - Health check endpoint
|
*/

Route::prefix('v1')->group(function () {
    Route::prefix('woocommerce')->name('woocommerce.')->group(function () {

        /*
        |--------------------------------------------------
        | Health Check
        |--------------------------------------------------
        | Used for monitoring & internal verification
        */
        Route::get('/health', function () {
            return response()->json([
                'status' => 'ok',
                'service' => 'woocommerce-integration',
                'timestamp' => now()->toIso8601String(),
                'message' => __('WooCommerce API is working!'),
            ]);
        })->name('health');

        /*
        |--------------------------------------------------
        | Webhooks
        |--------------------------------------------------
        | Note: Security is enforced via HMAC signature verification
        | in the controller.
        */
        Route::post('/webhooks/orders', [WooCommerceWebhookController::class, 'orders'])
            ->name('webhooks.orders');

        Route::post('/webhooks/products', [WooCommerceWebhookController::class, 'products'])
            ->name('webhooks.products');

        Route::post('/webhooks/uninstall', [WooCommerceWebhookController::class, 'uninstall'])
            ->name('webhooks.uninstall');
    });
});
