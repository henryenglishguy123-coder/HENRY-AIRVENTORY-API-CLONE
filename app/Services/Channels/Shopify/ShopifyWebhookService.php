<?php

declare(strict_types=1);

namespace App\Services\Channels\Shopify;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ShopifyWebhookService
{
    /**
     * Register required Shopify webhooks
     *
     * @throws \Exception
     */
    public function register(string $shop, string $accessToken): void
    {
        $webhooks = [
            'orders/create' => route('shopify.webhooks.orders'),
            'orders/updated' => route('shopify.webhooks.orders'),
            'orders/cancelled' => route('shopify.webhooks.orders'),
            'products/update' => route('shopify.webhooks.products'),
            'products/delete' => route('shopify.webhooks.products'),
            'app/uninstalled' => route('shopify.webhooks.uninstall'),
        ];
        foreach ($webhooks as $topic => $address) {
            try {
                $this->createWebhook(
                    shop: $shop,
                    token: $accessToken,
                    topic: $topic,
                    address: $address
                );
            } catch (\Exception $e) {
                // 'orders/create' might be restricted by Shopify and require approval.
                // We shouldn't fail the entire registration process if this specific webhook fails.
                if ($topic === 'orders/create') {
                    Log::warning("Failed to register optional webhook {$topic}: ".$e->getMessage());

                    continue;
                }
                throw $e;
            }
        }
    }

    /**
     * Create a single webhook subscription
     *
     * @throws \Exception
     */
    protected function createWebhook(
        string $shop,
        string $token,
        string $topic,
        string $address
    ): void {
        $apiVersion = config('services.shopify.api_version');
        if (empty($apiVersion)) {
            throw new \RuntimeException('Shopify API version is not configured (services.shopify.api_version).');
        }
        $response = Http::withHeaders([
            'X-Shopify-Access-Token' => $token,
            'Content-Type' => 'application/json',
        ])->timeout(10)->post(
            "https://{$shop}/admin/api/{$apiVersion}/webhooks.json",
            [
                'webhook' => [
                    'topic' => $topic,
                    'address' => $address,
                    'format' => 'json',
                ],
            ]
        );
        if ($response->successful()) {
            Log::info('Shopify webhook registered', [
                'shop' => $shop,
                'topic' => $topic,
            ]);

            return;
        }
        if ($response->status() === 422) {
            $body = $response->json();
            $errors = $body['errors'] ?? [];
            $isAlreadyTaken = false;

            // Check for common "already taken" messages in address or topic
            foreach (['address', 'topic'] as $field) {
                if (isset($errors[$field])) {
                    foreach ((array) $errors[$field] as $error) {
                        if (str_contains(strtolower($error), 'taken') || str_contains(strtolower($error), 'exist')) {
                            $isAlreadyTaken = true;
                            break 2;
                        }
                    }
                }
            }

            if ($isAlreadyTaken) {
                Log::info('Shopify webhook already exists', [
                    'shop' => $shop,
                    'topic' => $topic,
                ]);

                return;
            }

            Log::error('Shopify webhook validation failed', [
                'shop' => $shop,
                'topic' => $topic,
                'errors' => $errors,
            ]);
            throw new \Exception("Failed to register webhook {$topic}: Validation error");
        }
        Log::error('Shopify webhook registration failed', [
            'shop' => $shop,
            'topic' => $topic,
            'status' => $response->status(),
            'body' => $response->body(),
        ]);
        throw new \Exception("Failed to register webhook {$topic}. Status: ".$response->status());
    }
}
