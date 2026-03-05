<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Webhook;

use App\Http\Controllers\Controller;
use App\Jobs\WooCommerce\ProcessWooCommerceOrderJob;
use App\Jobs\WooCommerce\ProcessWooCommerceProductJob;
use App\Models\Customer\Store\VendorConnectedStore;
use App\Traits\WooCommerce\VerifiesWooCommerceSignature;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class WooCommerceWebhookController extends Controller
{
    use VerifiesWooCommerceSignature;

    /**
     * Handle WooCommerce Product Webhooks
     *
     * URL: /api/v1/woocommerce/webhooks/products?store_id=123
     */
    public function products(Request $request): JsonResponse
    {
        return $this->handleWebhook($request, 'product', ProcessWooCommerceProductJob::class);
    }

    /**
     * Handle WooCommerce Order Webhooks
     *
     * URL: /api/v1/woocommerce/webhooks/orders?store_id=123
     */
    public function orders(Request $request): JsonResponse
    {
        return $this->handleWebhook($request, 'order', ProcessWooCommerceOrderJob::class);
    }

    private function handleWebhook(Request $request, string $type, string $jobClass): JsonResponse
    {
        $storeIdInput = $request->query('store_id');

        if (! $storeIdInput || ! is_string($storeIdInput) || ! ctype_digit($storeIdInput)) {
            Log::warning("WooCommerce {$type} Webhook: Invalid or missing store_id in URL");

            return response()->json(['message' => 'Invalid store_id'], Response::HTTP_BAD_REQUEST);
        }

        $storeId = (int) $storeIdInput;

        $store = VendorConnectedStore::find($storeId);
        if (! $store) {
            Log::warning("WooCommerce {$type} Webhook: Store not found for ID {$storeId}");

            return response()->json(['message' => 'Store not found'], Response::HTTP_NOT_FOUND);
        }

        // Get Secret
        try {
            $tokenData = decrypt($store->token);
            $consumerSecret = $tokenData['consumer_secret'] ?? null;
        } catch (\Exception $e) {
            Log::error("WooCommerce Webhook: Failed to decrypt token for store {$store->id}");

            return response()->json(['message' => 'Server Error'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        // Verify Signature
        if (! $consumerSecret || ! $this->verifySignature($request, $consumerSecret)) {
            Log::warning("WooCommerce {$type} Webhook: Invalid signature for store {$storeId}");

            return response()->json(['message' => 'Invalid signature'], Response::HTTP_UNAUTHORIZED);
        }

        $topic = $request->header('X-WC-Webhook-Topic');
        if (! is_string($topic)) {
            $topic = '';
        }
        $payload = $request->all();

        Log::info("WooCommerce {$type} Webhook Received: {$topic}", [
            'store_id' => $storeId,
            'topic' => $topic,
        ]);

        // Dispatch Job logic
        if ($type === 'product') {
            if (! str_starts_with($topic, 'product.')) {
                Log::info("WooCommerce Webhook: Ignored topic {$topic} for product endpoint");

                return response()->json(['status' => 'ignored']);
            }
            $jobClass::dispatch($storeId, $topic, $payload);
        } elseif ($type === 'order') {
            // Basic check for order topics if needed, e.g. 'order.created', 'order.updated'
            if (! str_starts_with($topic, 'order.')) {
                Log::info("WooCommerce Webhook: Ignored topic {$topic} for order endpoint");

                return response()->json(['status' => 'ignored']);
            }
            $jobClass::dispatch($storeId, $topic, $payload);
        }

        return response()->json(['status' => 'success']);
    }
}
