<?php

declare(strict_types=1);

namespace App\Services\Channels\WooCommerce;

use App\Models\Customer\Designer\VendorDesignTemplateStore;
use App\Models\Customer\Store\VendorConnectedStore;
use App\Models\StoreChannels\StoreChannel;
use App\Services\Channels\Contracts\StoreConnectorInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WooCommerceConnector implements StoreConnectorInterface
{
    private const TIMEOUT_CONNECT = 60;

    private const TIMEOUT_REQUEST = 600;

    private const TIMEOUT_SYNC = 600;

    public function __construct(
        protected StoreChannel $channel,
        protected WooCommerceDataService $dataService
    ) {}

    public function buildAuthorizeUrl(
        int $vendorId,
        string $storeUrl,
        string $nonce
    ): string {
        $callbackUrl = route('callbacks.installed', [
            'channel' => $this->channel->code,
            'nonce' => $nonce,
            'store_url' => $storeUrl,
            'signature' => hash_hmac(
                'sha256',
                "{$vendorId}|{$nonce}",
                config('app.key')
            ),
        ]);

        return rtrim($storeUrl, '/').'/wc-auth/v1/authorize?'.http_build_query([
            'app_name' => config('app.name'),
            'scope' => 'read_write',
            'user_id' => $vendorId,
            'return_url' => rtrim(config('app.customer_panel_url'), '/').'/stores',
            'callback_url' => $callbackUrl,
        ]);
    }

    public function validateInstallCallback(Request $request): ?int
    {
        if (! $request->has(['nonce', 'signature'])) {
            return null;
        }
        $nonce = $request->nonce;
        $cached = Cache::pull("store_oauth_pending:{$nonce}");
        if (! $cached || ! isset($cached['vendor_id'], $cached['store_url'])) {
            return null;
        }
        $vendorId = (int) $cached['vendor_id'];
        $expected = hash_hmac(
            'sha256',
            "{$vendorId}|{$nonce}",
            config('app.key')
        );
        if (! hash_equals($expected, $request->signature)) {
            return null;
        }

        return $vendorId;
    }

    public function normalizeInstallPayload(array $payload): array
    {
        $payload = $payload['request'] ?? $payload;
        $required = ['consumer_key', 'consumer_secret', 'store_url'];
        foreach ($required as $field) {
            if (empty($payload[$field])) {
                throw new \InvalidArgumentException("Missing required field: {$field}");
            }
        }
        $parsedUrl = parse_url($payload['store_url']);
        if (! $parsedUrl || empty($parsedUrl['host'])) {
            throw new \InvalidArgumentException("Invalid store URL: {$payload['store_url']}");
        }
        if (($parsedUrl['scheme'] ?? '') !== 'https') {
            throw new \InvalidArgumentException("Store URL must use HTTPS: {$payload['store_url']}");
        }
        $link = rtrim($payload['store_url'], '/');
        $host = parse_url($link, PHP_URL_HOST);
        if (! $host) {
            throw new \InvalidArgumentException("Invalid store URL: {$link}");
        }
        $currency = $this->fetchStoreCurrency($link, $payload['consumer_key'], $payload['consumer_secret']);

        return [
            'store_identifier' => $host,
            'link' => $link,
            'token' => encrypt([
                'driver' => 'basic',
                'consumer_key' => $payload['consumer_key'],
                'consumer_secret' => $payload['consumer_secret'],
            ]),
            'currency' => $currency,
            'additional_data' => [
                'permissions' => $payload['key_permissions'] ?? null,
                'woo_user_id' => $payload['user_id'] ?? null,
                'key_id' => $payload['key_id'] ?? null,
            ],
        ];
    }

    public function getProductByExternalId(VendorConnectedStore $store, string $externalId): ?array
    {
        try {
            if (! ctype_digit($externalId)) {
                Log::warning('WooCommerce: invalid external product ID provided', [
                    'store_id' => $store->id,
                    'product_id' => $externalId,
                ]);

                return null;
            }
            $client = $this->getClient($store);
            /** @var \Illuminate\Http\Client\Response $response */
            $response = $client->get('products/'.rawurlencode($externalId));
            if (! $response->successful()) {
                $status = $response->status();
                if ($status === 404) {
                    Log::info('WooCommerce: product not found by ID', [
                        'store_id' => $store->id,
                        'product_id' => $externalId,
                        'status' => $status,
                    ]);
                } else {
                    Log::error('WooCommerce: failed to fetch product by ID', [
                        'store_id' => $store->id,
                        'product_id' => $externalId,
                        'status' => $status,
                    ]);
                }

                return null;
            }
            $data = $response->json();

            return is_array($data) ? $data : null;
        } catch (\Throwable $e) {
            Log::error('WooCommerce: getProductByExternalId exception', [
                'store_id' => $store->id ?? null,
                'product_id' => $externalId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    public function getVariationsByProductId(VendorConnectedStore $store, string $externalId): array
    {
        try {
            if (! ctype_digit($externalId)) {
                Log::warning('WooCommerce: invalid external product ID provided', [
                    'store_id' => $store->id,
                    'product_id' => $externalId,
                ]);

                return [];
            }
            $client = $this->getClient($store);
            $variations = $this->getAllVariations($client, $externalId);

            return $variations->map(function ($v) {
                return [
                    'id' => $v['id'] ?? null,
                    'sku' => $v['sku'] ?? null,
                    'attributes' => $v['attributes'] ?? [],
                ];
            })->values()->all();
        } catch (\Throwable $e) {
            Log::error('WooCommerce: getVariationsByProductId exception', [
                'store_id' => $store->id ?? null,
                'product_id' => $externalId,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    protected function fetchStoreCurrency(string $link, string $key, string $secret): ?string
    {
        try {
            /** @var \Illuminate\Http\Client\Response $response */
            $response = Http::withBasicAuth($key, $secret)
                ->timeout(self::TIMEOUT_REQUEST)
                ->get($link.'/wp-json/wc/v3/settings/general/woocommerce_currency');

            if ($response->successful()) {
                return $response->json('value');
            }

            Log::warning('Failed to fetch WooCommerce currency', [
                'status' => $response->status(),
                'body' => $response->body(),
                'link' => $link,
            ]);
        } catch (\Throwable $e) {
            Log::error('Exception fetching WooCommerce currency', [
                'error' => $e->getMessage(),
                'link' => $link,
            ]);
        }

        return null;
    }

    /**
     * Validate that the URL is safe to use (not private/reserved IP).
     *
     * @throws \Exception
     */
    protected function validateUrl(string $url): void
    {
        $parsed = parse_url($url);
        if (! isset($parsed['host'])) {
            throw new \Exception('Invalid URL: Host missing');
        }

        $host = $parsed['host'];
        $ips = gethostbynamel($host);

        if ($ips === false) {
            throw new \Exception('Invalid URL: Cannot resolve host');
        }

        foreach ($ips as $ip) {
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                throw new \Exception("Security violation: Resolved IP $ip is private or reserved");
            }
        }
    }

    public function verify(array $credentials): bool
    {
        if (! isset($credentials['consumer_key'], $credentials['consumer_secret'], $credentials['link'])) {
            return false;
        }

        try {
            $link = rtrim($credentials['link'], '/');
            $this->validateUrl($link);

            /** @var \Illuminate\Http\Client\Response $response */
            $response = Http::withBasicAuth(
                $credentials['consumer_key'],
                $credentials['consumer_secret']
            )->retry(2, 1000)
                ->connectTimeout(self::TIMEOUT_CONNECT)
                ->timeout(self::TIMEOUT_REQUEST)
                ->get($link.'/wp-json/wc/v3/system_status');

            if (! $response->successful()) {
                Log::warning('WooCommerce Verify Failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'link' => $link,
                ]);

                return false;
            }

            return true;
        } catch (\Throwable $e) {
            Log::error('WooCommerce Verify Exception', [
                'error' => $e->getMessage(),
                'link' => $credentials['link'] ?? 'N/A',
            ]);

            return false;
        }
    }

    /**
     * Fetch orders from WooCommerce with filtering and pagination.
     *
     * @param  string|null  $sinceDate  ISO 8601 date string (e.g., "2024-01-01T00:00:00Z")
     * @param  array  $statuses  Filter by order status (e.g., ['completed', 'processing'])
     * @param  int  $perPage  Number of orders per page (max 100)
     * @return array Array of order payloads
     *
     * @throws \RuntimeException
     */
    public function fetchOrders(
        VendorConnectedStore $store,
        ?string $sinceDate = null,
        array $statuses = ['completed', 'processing'],
        int $perPage = 100
    ): array {
        $allOrders = [];
        $page = 1;
        $maxPages = 100; // Safety limit
        $maxRetries = 3;

        try {
            $client = $this->getClient($store);
            do {
                // Sanitize and validate per_page parameter
                $perPageValue = max(1, min((int) $perPage ?: 1, 100));

                $params = [
                    'page' => $page,
                    'per_page' => $perPageValue,
                    'orderby' => 'date',
                    'order' => 'desc',
                ];

                if (! empty($statuses)) {
                    $params['status'] = implode(',', $statuses);
                }

                if ($sinceDate) {
                    $params['after'] = $sinceDate;
                }

                /** @var \Illuminate\Http\Client\Response $response */
                $response = null;
                $attempt = 0;

                // Retry logic for transient failures
                while ($attempt < $maxRetries) {
                    $attempt++;
                    $response = $client->get('orders', $params);

                    if ($response->successful()) {
                        break;
                    }

                    // Log the failure
                    Log::warning('WooCommerce orders fetch attempt failed', [
                        'attempt' => $attempt,
                        'max_retries' => $maxRetries,
                        'status' => $response->status(),
                        'store_id' => $store->id,
                        'page' => $page,
                    ]);

                    // If not last attempt, wait before retry
                    if ($attempt < $maxRetries) {
                        usleep(1000000 * $attempt); // 1s, 2s, 3s
                    }
                }

                // If still failed after retries, throw exception
                if ($response->failed()) {
                    throw new \RuntimeException(
                        "Failed to fetch WooCommerce orders for store {$store->id} (page {$page}) after {$attempt} attempts: ".
                        "HTTP {$response->status()} - {$response->body()}"
                    );
                }

                $orders = $response->json();

                if (empty($orders) || ! is_array($orders)) {
                    break;
                }

                // Use array_push with unpacking instead of array_merge for performance
                if (! empty($orders)) {
                    array_push($allOrders, ...$orders);
                }

                // Check if there are more pages
                $totalPages = (int) $response->header('X-WP-TotalPages', 1);
                if ($page >= $totalPages || $page >= $maxPages) {
                    break;
                }

                $page++;

                // Rate limiting: be gentle with WooCommerce stores
                usleep(200000); // 0.2 seconds

            } while (true);

            Log::info('Fetched WooCommerce orders', [
                'store_id' => $store->id,
                'count' => count($allOrders),
                'pages' => $page,
            ]);

            return $allOrders;
        } catch (\Throwable $e) {
            Log::error('Exception fetching WooCommerce orders', [
                'store_id' => $store->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    private function getClient(VendorConnectedStore $store): PendingRequest
    {
        $this->validateUrl($store->link);
        $token = decrypt($store->token);

        return Http::withHeaders([
            'Accept' => 'application/json',
        ])->withBasicAuth(
            $token['consumer_key'],
            $token['consumer_secret']
        )
            ->baseUrl(rtrim($store->link, '/').'/wp-json/wc/v3/')
            ->connectTimeout(self::TIMEOUT_CONNECT)
            ->timeout(self::TIMEOUT_SYNC);
    }

    public function syncProduct(VendorDesignTemplateStore $storeOverride): ?string
    {
        $wooId = $this->syncBaseProduct($storeOverride);

        if ($storeOverride->variants->isEmpty()) {
            $msg = 'Product must have at least one variant to be synced.';
            Log::warning('WooCommerce Sync: Product has no variants', ['store_override_id' => $storeOverride->id]);
            $storeOverride->update(['sync_status' => 'failed', 'sync_error' => $msg]);
            throw new \Exception($msg);
        }

        $this->syncVariations((string) $wooId, $storeOverride);

        $storeOverride->update([
            'sync_status' => 'synced',
            'sync_error' => null,
        ]);

        return (string) $wooId;
    }

    public function syncBaseProduct(VendorDesignTemplateStore $storeOverride): string
    {
        $store = $storeOverride->connectedStore ?? throw new \Exception('Store is not connected');
        $client = $this->getClient($store);

        $storeOverride->update(['sync_status' => 'syncing', 'sync_error' => null]);
        $this->dataService->ensureProductRelationships($storeOverride);

        $data = $this->dataService->prepareProductData($storeOverride);

        Log::info('Syncing product to WooCommerce', [
            'store_override_id' => $storeOverride->id,
            'name' => $data['name'] ?? null,
        ]);

        try {
            // No SKU-based linking; rely on external_product_id or creation

            $wooId = $this->createOrUpdateProduct($client, $storeOverride, $data);

            Log::info('Product synced to WooCommerce', [
                'woo_product_id' => $wooId,
                'store_override_id' => $storeOverride->id,
            ]);

            $storeOverride->update([
                'external_product_id' => $wooId,
                'sync_error' => null,
            ]);

            return (string) $wooId;

        } catch (\Throwable $e) {
            $this->handleSyncError($storeOverride, $e, $store->id);
            throw $e;
        }
    }

    /**
     * @throws RequestException
     * @throws ConnectionException
     * @throws \Exception
     */
    protected function createOrUpdateProduct(PendingRequest $client, VendorDesignTemplateStore $storeOverride, array $data): string
    {
        $externalId = $storeOverride->external_product_id;
        $response = null;

        if ($externalId) {
            $response = $client->put("products/{$externalId}", $data);

            if ($response->failed() && $response->status() === 404) {
                Log::info('Product not found on WooCommerce, creating new one.', ['external_id' => $externalId]);
                // Explicitly clear the external ID in the database
                $storeOverride->update(['external_product_id' => null]);
                $externalId = null;
            }
        }

        if (! $externalId) {
            $response = $client->post('products', $data);
        }

        if (! $response) {
            throw new \Exception('WooCommerce Sync Error: No response received');
        }

        if ($response->failed()) {
            $this->handleProductSyncFailure($response, $storeOverride);

            // If handleProductSyncFailure didn't throw (recovered), return the ID
            return (string) $storeOverride->external_product_id;
        }

        $productData = $response->json();
        if (! isset($productData['id'])) {
            throw new \Exception('WooCommerce Sync Response missing ID');
        }

        return (string) $productData['id'];
    }

    /**
     * @throws \Exception
     */
    protected function handleProductSyncFailure(\Illuminate\Http\Client\Response $response, VendorDesignTemplateStore $storeOverride): void
    {
        $status = $response->status();
        $body = $response->json();
        $message = $body['message'] ?? substr($response->body(), 0, 200);

        // Handle Duplicate SKU error
        if ($status === 400 && ($body['code'] ?? '') === 'product_invalid_sku' && isset($body['data']['resource_id'])) {
            $resourceId = $body['data']['resource_id'];
            Log::info("Recovered from Duplicate SKU error, using existing ID: $resourceId", ['store_override_id' => $storeOverride->id]);
            $storeOverride->update(['external_product_id' => $resourceId]);

            return;
        }

        Log::error('WooCommerce Product Sync Failed', [
            'status' => $status,
            'message' => $message,
            'store_override_id' => $storeOverride->id,
        ]);

        throw new \Exception("WooCommerce Sync Failed ({$status}): ".$message);
    }

    protected function handleSyncError(VendorDesignTemplateStore $storeOverride, \Throwable $e, int $storeId): void
    {
        $context = ['store_id' => $storeId, 'error' => $e->getMessage()];

        if ($e instanceof RequestException) {
            $context['status'] = $e->response?->status();
            $context['error'] = $e->response?->json('message') ?? $e->getMessage();
            Log::error('WooCommerce Request Exception', $context);
        } elseif ($e instanceof ConnectionException) {
            Log::error('WooCommerce Connection Timeout', $context);
            $storeOverride->update(['sync_error' => 'Connection timeout', 'sync_status' => 'failed']);

            return;
        } else {
            $context['trace'] = $e->getTraceAsString();
            Log::error('WooCommerce Unexpected Error', $context);
        }

        $storeOverride->update(['sync_error' => $e->getMessage(), 'sync_status' => 'failed']);
    }

    public function syncVariations(string $wooProductId, VendorDesignTemplateStore $storeOverride): void
    {
        Log::info('Preparing variations data for sync', [
            'store_override_id' => $storeOverride->id,
        ]);

        try {
            $store = $storeOverride->connectedStore ?? throw new \Exception('Store is not connected');
            $client = $this->getClient($store);

            $existingWooVariations = $this->getAllVariations($client, $wooProductId);
            $orphanedIds = $this->dataService->reconcileVariations($storeOverride, $existingWooVariations);

            if (! empty($orphanedIds)) {
                $this->deleteOrphanedVariations($client, $wooProductId, $orphanedIds);
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to reconcile existing variations, proceeding with standard sync', [
                'error' => $e->getMessage(),
                'store_override_id' => $storeOverride->id,
            ]);
        }

        foreach ($this->dataService->getVariationBatches($storeOverride) as $batch) {
            if (! empty($batch['create']) || ! empty($batch['update'])) {
                $this->syncVariationBatch($wooProductId, $storeOverride, $batch['create'], $batch['update']);
            }
        }
    }

    protected function deleteOrphanedVariations(PendingRequest $client, string $wooProductId, array $orphanedIds): void
    {
        Log::info('Deleting orphaned WooCommerce variations', [
            'count' => count($orphanedIds),
            'ids' => $orphanedIds,
            'product_id' => $wooProductId,
        ]);

        foreach (array_chunk($orphanedIds, 100) as $chunk) {
            $client->post("products/{$wooProductId}/variations/batch", [
                'delete' => $chunk,
            ]);
        }
    }

    protected function getAllVariations(PendingRequest $client, string $wooProductId): Collection
    {
        $allVariations = collect();
        $page = 1;
        $perPage = 100;

        do {
            /** @var \Illuminate\Http\Client\Response $response */
            $response = $client->get("products/{$wooProductId}/variations", [
                'page' => $page,
                'per_page' => $perPage,
            ]);

            if ($response->failed()) {
                Log::error('Failed to fetch WooCommerce variations page', [
                    'product_id' => $wooProductId,
                    'page' => $page,
                    'status' => $response->status(),
                ]);
                break;
            }

            $data = $response->json();
            if (empty($data)) {
                break;
            }

            $allVariations = $allVariations->merge($data);
            $page++;

        } while (count($data) >= $perPage);

        return $allVariations;
    }

    public function syncVariationBatch(string $wooProductId, VendorDesignTemplateStore $storeOverride, array $batchCreate, array $batchUpdate): void
    {
        $store = $storeOverride->connectedStore ?? throw new \Exception('Store is not connected');
        $client = $this->getClient($store);

        /** @var \Illuminate\Http\Client\Response $response */
        $response = $client->post("products/{$wooProductId}/variations/batch", [
            'create' => $batchCreate,
            'update' => $batchUpdate,
        ]);

        if (! $response->successful()) {
            $this->handleVariationBatchFailure($response, $wooProductId, $storeOverride);
        }

        $result = $response->json();
        $this->processVariationBatchResult($result, $wooProductId, $storeOverride);
    }

    protected function processVariationBatchResult(array $result, string $wooProductId, VendorDesignTemplateStore $storeOverride): void
    {
        $hasErrors = false;

        if (isset($result['create'])) {
            $hasErrors = $this->logVariationErrors($result['create'], 'Create', $wooProductId) || $hasErrors;
            $this->dataService->batchUpdateVariantExternalIds($storeOverride, $result['create']);
        }

        if (isset($result['update'])) {
            $hasErrors = $this->logVariationErrors($result['update'], 'Update', $wooProductId) || $hasErrors;
            // Handle variations that failed to update because they don't exist
            $this->handleInvalidVariationIds($storeOverride, $result['update']);
        }

        if ($hasErrors) {
            Log::warning('Some variations had errors during sync', [
                'woo_product_id' => $wooProductId,
                'store_override_id' => $storeOverride->id,
            ]);
        }
    }

    protected function handleInvalidVariationIds(VendorDesignTemplateStore $storeOverride, array $updateResults): void
    {
        $invalidIds = [];
        foreach ($updateResults as $item) {
            if (isset($item['error']['code']) && in_array($item['error']['code'], [
                'woocommerce_rest_product_invalid_id',
                'woocommerce_rest_term_invalid',
                'woocommerce_rest_cannot_edit',
                'woocommerce_rest_invalid_id', // Just in case
            ])) {
                $invalidIds[] = $item['id'];
            }
            // Also check for 404 status in the error object if present
            elseif (isset($item['error']['data']['status']) && $item['error']['data']['status'] === 404) {
                $invalidIds[] = $item['id'];
            }
        }

        if (! empty($invalidIds)) {
            Log::info('Clearing invalid external variant IDs', [
                'store_override_id' => $storeOverride->id,
                'invalid_ids' => $invalidIds,
            ]);

            $storeOverride->variants()
                ->whereIn('external_variant_id', $invalidIds)
                ->update(['external_variant_id' => null]);
        }
    }

    protected function logVariationErrors(array $items, string $action, string $wooProductId): bool
    {
        $hasErrors = false;
        foreach ($items as $item) {
            if (isset($item['error'])) {
                $hasErrors = true;
                Log::error("WooCommerce Variation {$action} Error", [
                    'error' => $item['error'],
                    'sku' => $item['sku'] ?? 'N/A',
                    'woo_product_id' => $wooProductId,
                ]);
            }
        }

        return $hasErrors;
    }

    /**
     * @throws \Exception
     */
    protected function handleVariationBatchFailure(\Illuminate\Http\Client\Response $response, string $wooProductId, VendorDesignTemplateStore $storeOverride): void
    {
        $status = $response->status();
        $message = $response->json('message') ?? substr($response->body(), 0, 200);

        Log::error('WooCommerce Variation Sync Batch Failed', [
            'status' => $status,
            'message' => $message,
            'woo_product_id' => $wooProductId,
            'store_override_id' => $storeOverride->id,
        ]);
        throw new \Exception('WooCommerce Variation Sync Failed: '.$message);
    }

    public function registerWebhooks(VendorConnectedStore $store): void
    {
        $client = $this->getClient($store);
        $existing = $this->getAllWebhooks($client);
        $tokenData = decrypt($store->token);
        $secret = $tokenData['consumer_secret'];

        $webhookMap = [
            'product.deleted' => 'woocommerce.webhooks.products',
            'order.created' => 'woocommerce.webhooks.orders',
            'order.updated' => 'woocommerce.webhooks.orders',
            'order.deleted' => 'woocommerce.webhooks.orders',
        ];

        foreach ($webhookMap as $topic => $routeName) {
            try {
                $deliveryUrl = route($routeName, ['store_id' => $store->id]);

                if ($this->webhookExists($existing, $topic, $deliveryUrl)) {
                    continue;
                }

                $this->createWebhook($client, $topic, $deliveryUrl, $secret);
            } catch (\Throwable $e) {
                Log::error('Failed to register WooCommerce webhook', [
                    'store_id' => $store->id,
                    'topic' => $topic,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    public function deleteWebhooks(VendorConnectedStore $store): void
    {
        $client = $this->getClient($store);
        $existing = $this->getAllWebhooks($client);

        foreach ($existing as $webhook) {
            // Delete webhooks that belong to our app (matched by delivery URL containing our route structure)
            if (str_contains($webhook['delivery_url'] ?? '', '/woocommerce/webhooks/') ||
                str_contains($webhook['delivery_url'] ?? '', '/webhooks/woocommerce/') || // Legacy check
                str_starts_with($webhook['name'] ?? '', 'Airventory')) {

                try {
                    Log::info('Deleting WooCommerce webhook', [
                        'store_id' => $store->id,
                        'webhook_id' => $webhook['id'],
                        'topic' => $webhook['topic'],
                    ]);

                    $response = $client->delete("webhooks/{$webhook['id']}", ['force' => true]);

                    if ($response->failed()) {
                        Log::error('Failed to delete WooCommerce webhook (API Error)', [
                            'store_id' => $store->id,
                            'webhook_id' => $webhook['id'],
                            'status' => $response->status(),
                            'body' => $response->body(),
                        ]);
                    }
                } catch (\Throwable $e) {
                    Log::error('Failed to delete WooCommerce webhook', [
                        'store_id' => $store->id,
                        'webhook_id' => $webhook['id'] ?? 'unknown',
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }
    }

    protected function getAllWebhooks(PendingRequest $client): Collection
    {
        $allWebhooks = collect();
        $page = 1;
        $perPage = 100;

        do {
            $response = $client->get('webhooks', [
                'page' => $page,
                'per_page' => $perPage,
            ]);

            if ($response->failed()) {
                Log::error('Failed to fetch WooCommerce webhooks page', [
                    'page' => $page,
                    'status' => $response->status(),
                ]);
                break;
            }

            $data = $response->json();
            if (empty($data)) {
                break;
            }

            $allWebhooks = $allWebhooks->merge($data);
            $page++;

        } while (count($data) >= $perPage);

        return $allWebhooks;
    }

    protected function webhookExists(Collection $existing, string $topic, string $deliveryUrl): bool
    {
        return $existing->contains(function ($webhook) use ($topic, $deliveryUrl) {
            return $webhook['topic'] === $topic && $webhook['delivery_url'] === $deliveryUrl;
        });
    }

    protected function createWebhook(PendingRequest $client, string $topic, string $deliveryUrl, string $secret): void
    {
        $client->post('webhooks', [
            'name' => 'Airventory '.$topic,
            'topic' => $topic,
            'delivery_url' => $deliveryUrl,
            'secret' => $secret,
        ]);
    }
}
