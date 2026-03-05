<?php

use App\Http\Controllers\Shopify\ShopifyFulfillmentController;
use App\Http\Controllers\Shopify\ShopifyGdprController;
use App\Http\Controllers\Shopify\ShopifyWebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Shopify Integration Routes
|--------------------------------------------------------------------------
| Headless Shopify app routes:
| - Webhook receivers
| - Health check endpoint
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function () {
    Route::prefix('shopify')->name('shopify.')->group(function () {

        /*
    |--------------------------------------------------
    | Health Check
    |--------------------------------------------------
    | Used for monitoring & internal verification
    */
        Route::get('/health', function () {
            return response()->json([
                'status' => 'ok',
                'service' => 'shopify-integration',
                'timestamp' => now()->toIso8601String(),
                'message' => __('Shopify API is working!'),
            ]);
        })->name('health');

        /*
        |--------------------------------------------------
        | Webhooks
        |--------------------------------------------------
        | Note: These routes are defined under the 'api' middleware group, so CSRF protection
        | is automatically disabled. Security is enforced via HMAC signature verification
        | in the controller/middleware layer.
        */
        Route::post('/webhooks/orders', [ShopifyWebhookController::class, 'orders'])
            ->name('webhooks.orders');

        Route::post('/webhooks/products', [ShopifyWebhookController::class, 'products'])
            ->name('webhooks.products');

        Route::post('/webhooks/uninstall', [ShopifyWebhookController::class, 'uninstall'])
            ->name('webhooks.uninstall');

        /*
        |--------------------------------------------------
        | Fulfillment Service Callback
        |--------------------------------------------------
        | Note: Incoming requests must be authenticated/verified via HMAC signature verification.
        */
        Route::post('/fulfillment/callback', [ShopifyFulfillmentController::class, 'callback'])
            ->name('fulfillment.callback');

        Route::post('/fulfillment/callback/fulfillment_order_notification', [ShopifyFulfillmentController::class, 'callback'])
            ->name('fulfillment.callback.notification');

        /*
        |--------------------------------------------------
        | GDPR Webhooks
        |--------------------------------------------------
        | Mandatory for public apps.
        | Verified via HMAC signature.
        */
        Route::post('/gdpr/customers/data_request', [ShopifyGdprController::class, 'customersDataRequest'])
            ->name('gdpr.customers.data_request');

        Route::post('/gdpr/customers/redact', [ShopifyGdprController::class, 'customersRedact'])
            ->name('gdpr.customers.redact');

        Route::post('/gdpr/shop/redact', [ShopifyGdprController::class, 'shopRedact'])
            ->name('gdpr.shop.redact');
    });
});
