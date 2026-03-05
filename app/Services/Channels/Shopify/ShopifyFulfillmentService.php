<?php

declare(strict_types=1);

namespace App\Services\Channels\Shopify;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ShopifyFulfillmentService
{
    public const SERVICE_NAME = 'Airventory Fulfillment';

    public const SERVICE_HANDLE = 'airventory-fulfillment';

    /**
     * Register the fulfillment service
     *
     * @return array|null Returns ['service_id' => ..., 'location_id' => ...] or null on failure
     *
     * @throws \Exception
     */
    public function register(string $shop, string $accessToken): ?array
    {
        // Validate shop domain to prevent SSRF
        if (! preg_match('/^[a-zA-Z0-9][a-zA-Z0-9-]*\.myshopify\.com$/', $shop)) {
            throw new \RuntimeException("Invalid shop domain: {$shop}");
        }

        $apiVersion = config('services.shopify.api_version');
        if (empty($apiVersion)) {
            throw new \RuntimeException('Shopify API version is not configured (services.shopify.api_version).');
        }

        $endpoint = "https://{$shop}/admin/api/{$apiVersion}/fulfillment_services.json";

        // 1. Check existing services
        $existing = $this->getExistingService($endpoint, $accessToken);

        if ($existing) {
            Log::info('Shopify Fulfillment Service already registered', [
                'shop' => $shop,
                'service_id' => $existing['id'],
                'location_id' => $existing['location_id'] ?? 'unknown',
                'handle' => $existing['handle'] ?? 'unknown',
            ]);

            return [
                'service_id' => $existing['id'],
                'location_id' => $existing['location_id'] ?? null,
                'handle' => $existing['handle'] ?? null,
            ];
        }

        // 2. Create Service
        $callbackUrl = route('shopify.fulfillment.callback');

        /** @var \Illuminate\Http\Client\Response $response */
        $response = Http::withHeaders([
            'X-Shopify-Access-Token' => $accessToken,
            'Content-Type' => 'application/json',
        ])->timeout(config('services.shopify.timeout', 30))->post($endpoint, [
            'fulfillment_service' => [
                'name' => self::SERVICE_NAME,
                'callback_url' => $callbackUrl,
                'inventory_management' => true,
                'tracking_support' => true,
                'requires_shipping_method' => true,
                'format' => 'json',
            ],
        ]);

        if ($response->successful()) {
            $data = $response->json('fulfillment_service');
            Log::info('Shopify Fulfillment Service registered successfully', [
                'shop' => $shop,
                'service_id' => $data['id'] ?? null,
                'location_id' => $data['location_id'] ?? null,
                'handle' => $data['handle'] ?? null,
            ]);

            return [
                'service_id' => $data['id'] ?? null,
                'location_id' => $data['location_id'] ?? null,
                'handle' => $data['handle'] ?? null,
            ];
        } else {
            Log::error('Failed to register Shopify Fulfillment Service', [
                'shop' => $shop,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \Exception('Failed to register Fulfillment Service: '.$response->body());
        }
    }

    private function getExistingService(string $endpoint, string $accessToken): ?array
    {
        try {
            /** @var \Illuminate\Http\Client\Response $response */
            $response = Http::withHeaders([
                'X-Shopify-Access-Token' => $accessToken,
            ])->timeout(config('services.shopify.timeout', 30))->get($endpoint, ['scope' => 'all']);

            if ($response->successful()) {
                $services = $response->json('fulfillment_services') ?? [];
                foreach ($services as $service) {
                    if ($service['name'] === self::SERVICE_NAME) {
                        return $service;
                    }
                }
            } else {
                Log::error('Failed to check existing Shopify Fulfillment Service', [
                    'service_name' => self::SERVICE_NAME,
                    'endpoint' => $endpoint,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('Exception while checking existing Shopify Fulfillment Service', [
                'service_name' => self::SERVICE_NAME,
                'endpoint' => $endpoint,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }
}
