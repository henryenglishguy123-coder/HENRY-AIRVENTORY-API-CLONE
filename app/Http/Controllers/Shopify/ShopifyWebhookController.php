<?php

declare(strict_types=1);

namespace App\Http\Controllers\Shopify;

use App\Http\Controllers\Controller;
use App\Jobs\Shopify\ProcessShopifyOrderJob;
use App\Jobs\Shopify\ProcessShopifyProductJob;
use App\Jobs\Shopify\ProcessShopifyUninstallJob;
use App\Traits\Shopify\VerifiesShopifySignature;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class ShopifyWebhookController extends Controller
{
    use VerifiesShopifySignature;

    /**
     * Handle Shopify webhook callback for orders
     */
    public function orders(Request $request): JsonResponse
    {
        return $this->handleWebhook($request, 'orders');
    }

    /**
     * Handle Shopify webhook callback for products
     */
    public function products(Request $request): JsonResponse
    {
        return $this->handleWebhook($request, 'products');
    }

    /**
     * Handle Shopify webhook callback for uninstallation
     */
    public function uninstall(Request $request): JsonResponse
    {
        return $this->handleWebhook($request, 'uninstall');
    }

    /**
     * Generic webhook handler
     */
    protected function handleWebhook(Request $request, string $type): JsonResponse
    {
        try {
            // 1. Validate Signature
            if (! $this->verifySignature($request)) {
                Log::warning('Shopify webhook signature verification failed', [
                    'shop' => $request->header('X-Shopify-Shop-Domain'),
                    'topic' => $request->header('X-Shopify-Topic'),
                ]);

                return response()->json([
                    'message' => 'Invalid signature',
                ], Response::HTTP_UNAUTHORIZED);
            }

            $shop = $request->header('X-Shopify-Shop-Domain');
            $topic = $request->header('X-Shopify-Topic');
            $payload = $request->all();

            Log::info("Shopify Webhook Received: {$type}", [
                'shop' => $shop,
                'topic' => $topic,
            ]);

            // 2. Dispatch Job based on type
            switch ($type) {
                case 'orders':
                    if ($topic === 'orders/create') {
                        ProcessShopifyOrderJob::dispatch($shop, $payload);
                    } else {
                        Log::info("Skipping Shopify order webhook for topic: {$topic}", [
                            'shop' => $shop,
                        ]);
                    }
                    break;
                case 'products':
                    ProcessShopifyProductJob::dispatch($shop, $topic, $payload);
                    break;
                case 'uninstall':
                    ProcessShopifyUninstallJob::dispatch($shop, $payload);
                    break;
                default:
                    Log::warning("Unhandled Shopify webhook type: {$type}", [
                        'shop' => $shop,
                        'topic' => $topic,
                    ]);

                    return response()->json([
                        'message' => "Unhandled webhook type: {$type}",
                    ], Response::HTTP_BAD_REQUEST);
            }

            return response()->json(['status' => 'success']);

        } catch (\Throwable $e) {
            Log::error('Shopify webhook processing failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Differentiate between transient (retryable) and persistent (non-retryable) errors
            if ($this->isTransientError($e)) {
                return response()->json([
                    'message' => 'Webhook processing failed (transient)',
                ], Response::HTTP_INTERNAL_SERVER_ERROR);
            }

            // Return 200 to acknowledge receipt and prevent Shopify from retrying indefinitely
            // for persistent errors (e.g., code bugs, invalid payloads)
            return response()->json([
                'status' => 'error_logged',
                'message' => 'Webhook processing failed (persistent)',
            ], Response::HTTP_OK);
        }
    }

    /**
     * Determine if the exception represents a transient error that warrants a retry.
     */
    protected function isTransientError(\Throwable $e): bool
    {
        // Database connection or locking issues
        if (($e instanceof \Illuminate\Database\QueryException) ||
            ($e instanceof \PDOException)) {
            return true;
        }

        // Check for specific class names if they exist (handling potential undefined classes)
        $className = get_class($e);
        if ($className === 'Illuminate\Database\ConnectionException' ||
            $className === 'RedisException') {
            return true;
        }

        // Redis or Queue connection issues based on message content
        if (str_contains(strtolower($e->getMessage()), 'connection') ||
            str_contains(strtolower($e->getMessage()), 'timeout') ||
            str_contains(strtolower($e->getMessage()), 'lock')) {
            return true;
        }

        return false;
    }
}
