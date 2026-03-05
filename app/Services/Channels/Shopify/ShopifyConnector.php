<?php

namespace App\Services\Channels\Shopify;

use App\Models\Customer\Designer\VendorDesignTemplateStore;
use App\Models\Customer\Store\VendorConnectedStore;
use App\Models\StoreChannels\StoreChannel;
use App\Services\Channels\Contracts\StoreConnectorInterface;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ShopifyConnector implements StoreConnectorInterface
{
    private const TIMEOUT_CONNECT = 60;

    private const TIMEOUT_REQUEST = 600;

    private ?string $accessToken = null;

    private ?string $shopDomain = null;

    private ?array $shopDetails = null;

    protected ?ShopifyDataService $dataService = null;

    public function __construct(
        protected StoreChannel $channel,
        ?ShopifyDataService $dataService = null
    ) {
        $this->dataService = $dataService ?? app(ShopifyDataService::class);
    }

    public function buildAuthorizeUrl(int $vendorId, string $storeUrl, string $nonce): string
    {
        $apiKey = config('services.shopify.key');
        $scopes = config('services.shopify.scopes');

        if (empty($apiKey)) {
            throw new \RuntimeException('Shopify API key is not configured (services.shopify.key).');
        }

        if (empty($scopes)) {
            throw new \RuntimeException('Shopify scopes are not configured (services.shopify.scopes).');
        }

        $redirectUri = route('callbacks.installed', ['channel' => 'shopify']);

        // Ensure store URL has protocol for parse_url
        if (! preg_match('#^https?://#', $storeUrl)) {
            $storeUrl = 'https://'.$storeUrl;
        }

        $shopDomain = parse_url($storeUrl, PHP_URL_HOST);
        // Fallback to manual extraction if parse_url fails
        if (! $shopDomain) {
            $shopDomain = preg_replace('#^https?://#', '', $storeUrl);
            $shopDomain = rtrim($shopDomain, '/');
        }

        return "https://{$shopDomain}/admin/oauth/authorize?".http_build_query([
            'client_id' => $apiKey,
            'scope' => $scopes,
            'redirect_uri' => $redirectUri,
            'state' => $nonce,
        ]);
    }

    public function normalizeInstallPayload(array $payload): array
    {
        if (! $this->accessToken) {
            throw new \RuntimeException('Access token not found. Validation must be called first.');
        }

        $shop = $payload['shop'] ?? null;
        if (! $shop) {
            throw new \InvalidArgumentException('Missing shop parameter');
        }

        $currency = $this->fetchStoreCurrency($shop);

        return [
            'store_identifier' => $shop,
            'link' => "https://{$shop}",
            'token' => encrypt([
                'access_token' => $this->accessToken,
                'shop' => $shop,
            ]),
            'currency' => $currency,
            'additional_data' => $this->shopDetails ? ['associated_user' => $this->shopDetails] : [],
        ];
    }

    protected function fetchStoreCurrency(string $shop): ?string
    {
        try {
            $apiVersion = config('services.shopify.api_version', '2024-01');
            if ($apiVersion) {
                /** @var \Illuminate\Http\Client\Response $response */
                $response = Http::withHeaders([
                    'X-Shopify-Access-Token' => $this->accessToken,
                ])->timeout(self::TIMEOUT_REQUEST)->get("https://{$shop}/admin/api/{$apiVersion}/shop.json");

                if ($response->successful()) {
                    return $response->json('shop.currency');
                }

                Log::warning('Failed to fetch Shopify currency', [
                    'shop' => $shop,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('Exception fetching Shopify currency', [
                'error' => $e->getMessage(),
                'shop' => $shop,
            ]);
        }

        return null;
    }

    public function validateInstallCallback(Request $request): ?int
    {
        // 1. Basic Validation
        if (! $request->has(['shop', 'code', 'state', 'hmac'])) {
            Log::warning('Shopify callback missing parameters', [
                'shop' => $request->input('shop'),
                'state' => $request->input('state'),
            ]);

            return null;
        }

        $shop = $request->shop;
        $code = $request->code;
        $state = $request->state;
        $hmac = $request->hmac;

        // 2. Verify State (Nonce)
        $cached = Cache::pull("store_oauth_pending:{$state}");
        if (! $cached || ! isset($cached['vendor_id'])) {
            Log::warning('Shopify callback invalid state', ['state' => $state]);

            return null;
        }
        $vendorId = (int) $cached['vendor_id'];

        // 3. Verify HMAC
        $params = $request->query();
        unset($params['hmac'], $params['signature']);
        ksort($params);

        $queryString = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
        $computedHmac = hash_hmac('sha256', $queryString, config('services.shopify.secret'));

        if (! hash_equals($hmac, $computedHmac)) {
            Log::warning('Shopify callback HMAC mismatch', ['shop' => $shop]);

            return null;
        }

        // 4. Exchange Code for Access Token
        try {
            if (! preg_match('/^[a-zA-Z0-9][a-zA-Z0-9-]*\.myshopify\.com$/', $shop)) {
                Log::warning('Shopify callback invalid shop domain', ['shop' => $shop]);

                return null;
            }

            $response = Http::timeout(self::TIMEOUT_REQUEST)->post("https://{$shop}/admin/oauth/access_token", [
                'client_id' => config('services.shopify.key'),
                'client_secret' => config('services.shopify.secret'),
                'code' => $code,
            ]);

            if ($response->failed()) {
                Log::error('Shopify access token exchange failed', [
                    'status' => $response->status(),
                    'message' => 'Token exchange failed',
                ]);

                return null;
            }

            $data = $response->json();
            $this->accessToken = $data['access_token'] ?? null;
            $this->shopDetails = $data['associated_user'] ?? null;

            if (! $this->accessToken) {
                return null;
            }

            return $vendorId;
        } catch (\Exception $e) {
            Log::error('Shopify token exchange exception', ['error' => $e->getMessage()]);

            return null;
        }
    }

    public function getProductByExternalId(VendorConnectedStore $store, string $externalId): ?array
    {
        try {
            $this->ensureClient($store);
            if (! $this->accessToken || ! $this->shopDomain) {
                Log::warning('Shopify: missing credentials for store', [
                    'store_id' => $store->id,
                    'reason' => 'accessToken/shopDomain not set',
                ]);

                return null;
            }

            $id = $externalId;
            if (str_starts_with($externalId, 'gid://shopify/Product/')) {
                $id = (string) preg_replace('#^gid://shopify/Product/#', '', $externalId);
            }
            $response = $this->request('GET', "products/{$id}.json");
            if ($response->failed()) {
                $status = $response->status();
                if ($status === 404) {
                    Log::info('Shopify: product not found by ID', [
                        'store_id' => $store->id,
                        'product_id' => $externalId,
                        'status' => $status,
                    ]);

                    return null;
                }
                Log::error('Shopify: error fetching product by ID', [
                    'store_id' => $store->id,
                    'product_id' => $externalId,
                    'status' => $status,
                    'body' => $response->body(),
                ]);

                return null;
            }
            $data = $response->json('product') ?? $response->json();
            $summary = is_array($data) ? [
                'id' => $data['id'] ?? null,
                'title' => $data['title'] ?? null,
                'handle' => $data['handle'] ?? null,
                'variants_count' => (isset($data['variants']) && is_array($data['variants'])) ? count($data['variants']) : null,
            ] : null;
            Log::info('Shopify: product found by ID', [
                'store_id' => $store->id,
                'product_id' => $externalId,
                'product_summary' => $summary,
            ]);

            return is_array($data) ? $data : null;
        } catch (\Throwable $e) {
            Log::error('Shopify: getProductByExternalId exception', ['store_id' => $store->id ?? null, 'product_id' => $externalId, 'error' => $e->getMessage()]);

            return null;
        }
    }

    public function verify(array $credentials): bool
    {
        $accessToken = $credentials['access_token'] ?? null;

        // Prefer full shop link if provided; fall back to shop domain.
        $shop = $credentials['link'] ?? ($credentials['shop'] ?? '');
        $shop = trim($shop);

        // Normalize shop value to a full URL and clean up trailing slashes.
        if ($shop && ! preg_match('#^https?://#i', $shop)) {
            $shop = 'https://'.$shop;
        }
        $shop = rtrim($shop, '/');

        if (! $accessToken || ! $shop) {
            return false;
        }

        try {
            $apiVersion = config('services.shopify.api_version');

            if (empty($apiVersion)) {
                Log::error('Shopify API version is not configured.');

                return false;
            }

            $response = Http::withHeaders([
                'X-Shopify-Access-Token' => $accessToken,
            ])->connectTimeout(self::TIMEOUT_CONNECT)->timeout(self::TIMEOUT_REQUEST)->get("{$shop}/admin/api/{$apiVersion}/shop.json");

            if ($response->failed()) {
                Log::warning('Shopify verification failed', [
                    'shop' => $shop,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            }

            return $response->successful();
        } catch (\Exception $e) {
            Log::error('Shopify verification exception', ['error' => $e->getMessage()]);

            return false;
        }
    }

    public function syncProduct(VendorDesignTemplateStore $storeOverride): ?string
    {
        Log::info('ShopifyConnector::syncProduct called', ['store_override_id' => $storeOverride->id]);

        $store = $storeOverride->connectedStore ?? throw new \Exception('Store is not connected');
        $this->ensureFulfillmentServiceRegistered($store);
        $handle = $store->additional_data['fulfillment_service_handle'] ?? null;
        if (! is_string($handle) || $handle === '') {
            $this->ensureClient($store);
            $service = app(ShopifyFulfillmentService::class);
            $result = $service->register($this->shopDomain, $this->accessToken);
            $handle = $result['handle'] ?? ShopifyFulfillmentService::SERVICE_HANDLE;
            $data = $store->additional_data ?? [];
            $data['fulfillment_service_id'] = $result['service_id'] ?? ($data['fulfillment_service_id'] ?? null);
            $data['location_id'] = $result['location_id'] ?? ($data['location_id'] ?? null);
            $data['fulfillment_service_handle'] = $handle;
            $store->additional_data = $data;
            $store->save();
        }

        try {
            $shopifyId = $this->syncBaseProduct($storeOverride, $handle);

            // Sync variations (Create/Update)
            $payload = $this->dataService->prepareVariationsData($storeOverride, $handle);
            $syncStatus = 'synced';
            $syncError = null;

            if ($shopifyId && (! empty($payload['create']) || ! empty($payload['update']))) {
                $result = $this->syncVariationBatch($shopifyId, $storeOverride, $payload['create'], $payload['update']);

                if (! empty($result['errors'])) {
                    if ($result['success'] === 0 && $result['total'] > 0) {
                        $syncStatus = 'failed';
                        $syncError = "All {$result['total']} variants failed to sync. Errors: ".implode('; ', array_slice($result['errors'], 0, 3));
                    } else {
                        $syncStatus = 'partial';
                        $syncError = count($result['errors']).' variants failed to sync. Errors: '.implode('; ', array_slice($result['errors'], 0, 3));
                    }
                }
            } elseif ($shopifyId) {
                // If no variation updates, we still need to reconcile deletions
                $this->reconcileVariations($shopifyId, $storeOverride);
            }

            // Finalize Sync Status
            $storeOverride->update([
                'sync_status' => $syncStatus,
                'sync_error' => $syncError ? \Illuminate\Support\Str::limit($syncError, 252, '...') : null,
                'external_product_id' => $shopifyId,
            ]);

            return $shopifyId;
        } catch (\Throwable $e) {
            $storeOverride->update([
                'sync_status' => 'failed',
                'sync_error' => $e->getMessage(),
            ]);
            Log::error('ShopifyConnector::syncProduct failed', [
                'store_override_id' => $storeOverride->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    public function syncBaseProduct(VendorDesignTemplateStore $storeOverride, ?string $fulfillmentServiceHandle = null): string
    {
        $store = $storeOverride->connectedStore ?? throw new \Exception('Store is not connected');
        $this->ensureClient($store);

        $storeOverride->update(['sync_status' => 'syncing', 'sync_error' => null]);
        $this->dataService->ensureProductRelationships($storeOverride);

        $data = $this->dataService->prepareProductData($storeOverride);
        $externalId = $storeOverride->external_product_id;

        try {
            if ($externalId) {
                $externalId = $this->updateExistingProduct($externalId, $data, $storeOverride);
                if ($externalId) {
                    $this->syncProductImages($externalId, $storeOverride);
                }
            }

            if (! $externalId) {
                $externalId = $this->createNewProduct($storeOverride, $data, $fulfillmentServiceHandle);
                if ($externalId) {
                    $this->publishToAllMarkets($externalId);
                }
            }

            // creation handled above

            $storeOverride->update([
                'external_product_id' => $externalId,
                'sync_status' => 'syncing',
                'sync_error' => null,
            ]);

            return $externalId;
        } catch (\Throwable $e) {
            $storeOverride->update(['sync_status' => 'failed', 'sync_error' => $e->getMessage()]);
            throw $e;
        }
    }

    protected function syncProductImages(string $shopifyProductId, VendorDesignTemplateStore $storeOverride): void
    {
        try {
            // 1. Fetch existing images from Shopify
            $response = $this->request('GET', "products/{$shopifyProductId}/images.json");
            if ($response->failed()) {
                Log::error("Shopify: Failed to fetch images for product {$shopifyProductId}", ['error' => $response->body()]);

                return;
            }
            $shopifyImages = $response->json('images') ?? [];

            // Map existing filenames (heuristics based)
            $existingFilenames = [];
            foreach ($shopifyImages as $img) {
                if (isset($img['src'])) {
                    $path = parse_url($img['src'], PHP_URL_PATH);
                    $filename = basename($path);
                    $existingFilenames[$filename] = $img['id'];
                }
            }

            // 2. Get local images
            $localImages = $this->dataService->getOrderedImages($storeOverride);

            foreach ($localImages as $localImg) {
                $path = $localImg['path'] ?? '';
                if (! $path) {
                    continue;
                }

                $localFilename = basename($path);

                // Check if likely exists
                $found = false;
                foreach ($existingFilenames as $fName => $id) {
                    // Check if Shopify filename contains our local filename (Shopify adds hashes/versions)
                    // or exact match
                    if ($fName === $localFilename || str_contains($fName, pathinfo($localFilename, PATHINFO_FILENAME))) {
                        $found = true;
                        break;
                    }
                }

                if (! $found) {
                    Log::info("Shopify: Uploading missing image {$localFilename} for product {$shopifyProductId}");
                    $this->request('POST', "products/{$shopifyProductId}/images.json", [
                        'image' => [
                            'src' => $localImg['src'],
                            // Not sending position to avoid conflict/reordering logic complexity
                        ],
                    ]);
                }
            }
        } catch (\Throwable $e) {
            Log::error('Shopify: syncProductImages failed', ['error' => $e->getMessage()]);
        }
    }

    protected function updateExistingProduct(string $externalId, array $data, VendorDesignTemplateStore $storeOverride): ?string
    {
        // Prevent image overwrites during base sync. Images are handled by ensureProductImages.
        if (isset($data['product']['images'])) {
            unset($data['product']['images']);
        }

        // Fetch current data for selective update
        $currentResponse = $this->request('GET', "products/{$externalId}.json");

        if ($currentResponse->failed()) {
            if ($currentResponse->status() === 404) {
                // Product deleted on Shopify, return null to trigger recreation
                Log::info("Shopify: Product {$externalId} not found (404), clearing external_product_id to trigger recreation.");
                $storeOverride->update(['external_product_id' => null]);

                return null;
            }
            // If fetch fails, we skip update to avoid overwriting with stale/partial data
            throw new \Exception('Shopify Update Failed (Fetch): '.$currentResponse->body());
        }

        $currentProduct = $currentResponse->json('product');
        $newProduct = $data['product'];
        $updatePayload = [];

        // Fields to compare
        $fields = ['title', 'body_html', 'vendor', 'product_type', 'handle', 'status', 'tags', 'published_scope'];

        foreach ($fields as $field) {
            if (array_key_exists($field, $newProduct) && array_key_exists($field, $currentProduct)) {
                $val1 = $newProduct[$field];
                $val2 = $currentProduct[$field];

                if ($field === 'tags') {
                    // Normalize tags (Shopify returns comma-separated string)
                    $t1 = collect(explode(',', (string) $val1))->map(fn ($t) => trim($t))->sort()->values()->all();
                    $t2 = collect(explode(',', (string) $val2))->map(fn ($t) => trim($t))->sort()->values()->all();
                    if ($t1 != $t2) {
                        $updatePayload[$field] = $val1;
                    }
                } elseif ($field === 'body_html') {
                    // Simple normalization for HTML might be needed, but exact match is safer
                    if ($val1 !== $val2) {
                        $updatePayload[$field] = $val1;
                    }
                } else {
                    if ($val1 != $val2) {
                        $updatePayload[$field] = $val1;
                    }
                }
            } elseif (array_key_exists($field, $newProduct)) {
                $updatePayload[$field] = $newProduct[$field];
            }
        }

        if (empty($updatePayload)) {
            Log::info("Shopify: No changes for product {$externalId}, skipping update.");

            return $externalId;
        }

        // Send only changed fields
        $response = $this->request('PUT', "products/{$externalId}.json", ['product' => array_merge(['id' => $externalId], $updatePayload)]);

        if ($response->failed()) {
            if ($response->status() === 404) {
                return null;
            }
            throw new \Exception('Shopify Update Failed: '.$response->body());
        }

        return $externalId;
    }

    /**
     * Assign the Airventory fulfillment service to the specified existing Shopify variants
     * without touching product title/body/images or other fields.
     *
     * This is intended for "fulfillment-only" linking flows where products already exist
     * on Shopify and we simply want Shopify orders for those variants to route to our service.
     *
     * @param  string  $externalProductId  Numeric Shopify product ID or GID
     * @param  array<int,string>  $externalVariantIds  Array of variant IDs (numeric or GID)
     * @return int Number of variants successfully updated
     */
    public function assignFulfillmentServiceToExistingProduct(
        \App\Models\Customer\Store\VendorConnectedStore $store,
        string $externalProductId,
        array $externalVariantIds
    ): int {
        $this->ensureClient($store);
        $this->ensureFulfillmentServiceRegistered($store);

        $handle = $store->additional_data['fulfillment_service_handle'] ?? null;
        if (! is_string($handle) || $handle === '') {
            Log::error('Shopify: Fulfillment service handle missing or invalid after registration attempt', [
                'store_id' => $store->id ?? null,
                'product_id' => $externalProductId,
                'variant_ids' => $externalVariantIds,
            ]);

            // Do not proceed with assigning an unknown or unregistered fulfillment service.
            return 0;
        }
        // Normalize IDs
        $normalize = static function (string $id): string {
            if (str_starts_with($id, 'gid://shopify/ProductVariant/')) {
                return (string) preg_replace('#^gid://shopify/ProductVariant/#', '', $id);
            }

            return $id;
        };

        $variantIds = collect($externalVariantIds)
            ->filter()
            ->map(fn ($v) => (string) $v)
            ->map($normalize)
            ->unique()
            ->values()
            ->all();

        $updated = 0;
        foreach ($variantIds as $vid) {
            try {
                $response = $this->request('PUT', "variants/{$vid}.json", [
                    'variant' => [
                        'id' => $vid,
                        'fulfillment_service' => $handle,
                        // Ensure Shopify doesn't try to manage inventory on their side when using our service
                        'inventory_management' => null,
                    ],
                ]);
                if ($response->successful()) {
                    $updated++;
                } else {
                    Log::warning('Shopify: Failed assigning fulfillment service to variant', [
                        'variant_id' => $vid,
                        'status' => $response->status(),
                        'body' => $response->body(),
                    ]);
                }
            } catch (\Throwable $e) {
                Log::error('Shopify: Exception assigning fulfillment service to variant', [
                    'variant_id' => $vid,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('Shopify: Fulfillment service assignment completed', [
            'product_id' => $externalProductId,
            'variant_count' => count($variantIds),
            'updated' => $updated,
            'handle' => $handle,
        ]);

        return $updated;
    }

    protected function createNewProduct(VendorDesignTemplateStore $storeOverride, array $data, ?string $fulfillmentServiceHandle = null): string
    {
        // If creating a product with options, we must include variants to satisfy Shopify requirements.
        if (isset($data['product']['options']) && ! empty($data['product']['options'])) {
            $this->appendInitialVariants($storeOverride, $data, $fulfillmentServiceHandle);
        }

        $response = $this->request('POST', 'products.json', $data);
        if ($response->failed()) {
            $body = $response->body();

            if ($fulfillmentServiceHandle && str_contains($body, '"fulfillment_service"') && str_contains($body, 'is not defined for your shop')) {
                $store = $storeOverride->connectedStore ?? throw new \Exception('Store is not connected');
                $this->ensureClient($store);

                $service = app(ShopifyFulfillmentService::class);
                $service->register($this->shopDomain, $this->accessToken);

                $response = $this->request('POST', 'products.json', $data);
                if ($response->failed()) {
                    throw new \Exception('Shopify Create Failed: '.$response->body());
                }
            } else {
                throw new \Exception('Shopify Create Failed: '.$body);
            }
        }

        $productData = $response->json('product');
        $externalId = (string) $productData['id'];

        $this->processCreatedProductVariants($productData, $storeOverride, $externalId);

        return $externalId;
    }

    protected function appendInitialVariants(VendorDesignTemplateStore $storeOverride, array &$data, ?string $fulfillmentServiceHandle = null): void
    {
        // Shopify allows creating a maximum of 100 variants during product creation.
        // Any additional variants will be handled by the subsequent syncVariationBatch process,
        // which identifies variants missing external_variant_id and creates them in batches.
        $variantsToSync = $storeOverride->variants->take(100);
        $variantsPayload = [];

        $sortedOptionNames = $this->dataService->getSortedOptionNames($storeOverride);

        foreach ($variantsToSync as $variant) {
            $vData = $this->dataService->prepareVariantData(
                $variant,
                $storeOverride,
                $sortedOptionNames,
                null,
                $fulfillmentServiceHandle
            );
            if (isset($vData['variant'])) {
                // Remove _cost before sending to Shopify (REST API doesn't support it on variant creation)
                if (isset($vData['variant']['_cost'])) {
                    unset($vData['variant']['_cost']);
                }
                $variantsPayload[] = $vData['variant'];
            }
        }

        if (! empty($variantsPayload)) {
            $data['product']['variants'] = $variantsPayload;
        }
    }

    protected function processCreatedProductVariants(array $productData, VendorDesignTemplateStore $storeOverride, string $externalId): void
    {
        // Build Image Map (Position -> ID)
        $imageMap = [];
        if (isset($productData['images']) && is_array($productData['images'])) {
            foreach ($productData['images'] as $img) {
                if (isset($img['position'], $img['id'])) {
                    $imageMap[$img['position']] = $img['id'];
                }
            }
        }

        // Map created variants to local DB and Link Images
        if (isset($productData['variants']) && is_array($productData['variants'])) {
            $variantsToUpdateImage = [];

            foreach ($productData['variants'] as $shopifyVariant) {
                $sku = $shopifyVariant['sku'] ?? null;
                if ($sku) {
                    $localVariant = $storeOverride->variants()->where('sku', $sku)->first();

                    if ($localVariant) {
                        // 1. Update External ID
                        $localVariant->update(['external_variant_id' => $shopifyVariant['id']]);

                        // 2. Update Cost
                        try {
                            $cost = $this->dataService->getRawVendorCost($localVariant, $storeOverride);
                            if ($cost > 0) {
                                $this->updateVariantCost($shopifyVariant['id'], (string) $cost);
                            }
                        } catch (\Throwable $e) {
                            Log::error('Shopify: Failed to update cost for initial variant', [
                                'variant_id' => $shopifyVariant['id'],
                                'error' => $e->getMessage(),
                            ]);
                        }

                        // 3. Link Image if available
                        $position = $this->dataService->getVariantImagePosition($localVariant, $storeOverride);
                        if ($position && isset($imageMap[$position])) {
                            $imageId = $imageMap[$position];
                            $variantsToUpdateImage[] = [
                                'id' => $shopifyVariant['id'],
                                'image_id' => $imageId,
                            ];
                        }
                    }
                }
            }

            // Batch update variant images using Product Update endpoint
            if (! empty($variantsToUpdateImage)) {
                $this->batchUpdateVariantImages($externalId, $variantsToUpdateImage);
            }
        }
    }

    protected function batchUpdateVariantImages(string $productId, array $variantsToUpdateImage): void
    {
        try {
            $this->request('PUT', "products/{$productId}.json", [
                'product' => [
                    'id' => $productId,
                    'variants' => $variantsToUpdateImage,
                ],
            ]);
            Log::info('Shopify: Batch updated variant images', [
                'count' => count($variantsToUpdateImage),
                'product_id' => $productId,
            ]);
        } catch (\Throwable $e) {
            Log::error('Shopify: Failed to batch update variant images', [
                'product_id' => $productId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function reconcileVariations(string $shopifyProductId, VendorDesignTemplateStore $storeOverride): void
    {
        try {
            // Guard: If we have no local variants, do not delete everything blindly.
            if ($storeOverride->variants->isEmpty()) {
                Log::warning('Shopify: Skipping reconciliation - No local variants found', [
                    'product_id' => $shopifyProductId,
                    'store_override_id' => $storeOverride->id,
                ]);

                return;
            }

            // 1. Fetch all existing variants from Shopify
            $response = $this->request('GET', "products/{$shopifyProductId}/variants.json");

            if ($response->failed()) {
                Log::error('Shopify: Failed to fetch variants for reconciliation', [
                    'product_id' => $shopifyProductId,
                    'error' => $response->body(),
                ]);

                return;
            }

            $shopifyVariants = $response->json('variants') ?? [];

            // Map local variants by SKU for easy lookup
            $localVariantsBySku = $storeOverride->variants->whereNotNull('sku')->keyBy('sku');

            // Map local variants by Options Signature for fallback matching
            $sortedOptionNames = $this->dataService->getSortedOptionNames($storeOverride);
            $localVariantsByOptions = [];
            foreach ($storeOverride->variants as $variant) {
                $options = $this->dataService->getVariantOptions($variant, $sortedOptionNames);
                // Use strict JSON encoding for signature
                $localVariantsByOptions[json_encode($options)] = $variant;
            }

            // Identify variants to delete and Link existing ones
            $variantsToDelete = [];

            foreach ($shopifyVariants as $shopifyVariant) {
                $shopifySku = $shopifyVariant['sku'] ?? null;
                $localVariant = null;

                // 1. Try SKU Match
                if ($shopifySku && $localVariantsBySku->has($shopifySku)) {
                    $localVariant = $localVariantsBySku->get($shopifySku);
                }

                // 2. Fallback: Try Option Match
                if (! $localVariant) {
                    $sOptions = [];
                    for ($i = 1; $i <= 3; $i++) {
                        if (isset($shopifyVariant['option'.$i]) && $shopifyVariant['option'.$i] !== null) {
                            $sOptions[] = $shopifyVariant['option'.$i];
                        }
                    }
                    $signature = json_encode($sOptions);
                    if (isset($localVariantsByOptions[$signature])) {
                        $localVariant = $localVariantsByOptions[$signature];
                    }
                }

                // If found (by SKU or Options), Link it
                if ($localVariant) {
                    // Link if missing or different
                    if ((string) $localVariant->external_variant_id !== (string) $shopifyVariant['id']) {
                        $localVariant->update(['external_variant_id' => $shopifyVariant['id']]);
                        Log::info('Shopify: Linked existing variant during reconciliation', [
                            'sku' => $shopifySku,
                            'local_id' => $localVariant->id,
                            'shopify_id' => $shopifyVariant['id'],
                            'method' => $shopifySku && $localVariantsBySku->has($shopifySku) ? 'sku' : 'options',
                        ]);
                    }
                } else {
                    // SKU is empty or not in our local list -> Mark for deletion.
                    $variantsToDelete[] = $shopifyVariant;
                }
            }

            // Check if deleting these would remove ALL variants (which Shopify disallows with 422)
            $totalShopifyCount = count($shopifyVariants);
            $plannedDeletes = count($variantsToDelete);

            if ($plannedDeletes > 0 && ($totalShopifyCount - $plannedDeletes <= 0)) {
                // If we are about to delete everything, we must skip the last one to avoid 422.
                // We'll skip the last one in the list.
                $skippedVariant = array_pop($variantsToDelete);

                // Option A: Archive the product to hide the orphan variant
                try {
                    $this->request('PUT', "products/{$shopifyProductId}.json", [
                        'product' => [
                            'id' => $shopifyProductId,
                            'status' => 'archived',
                        ],
                    ]);

                    Log::warning('Shopify Reconciliation: Archived product because all variants were slated for deletion. Kept one variant to satisfy Shopify requirements.', [
                        'product_id' => $shopifyProductId,
                        'skipped_variant_id' => $skippedVariant['id'],
                        'skipped_sku' => $skippedVariant['sku'] ?? 'N/A',
                    ]);
                } catch (\Throwable $e) {
                    Log::error('Shopify Reconciliation: Failed to archive product', [
                        'product_id' => $shopifyProductId,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Execute Deletions with Rate Limiting
            foreach ($variantsToDelete as $variant) {
                $this->deleteVariant($shopifyProductId, $variant['id'], $variant['sku'] ?? null);
                // Simple rate limiter: 0.5s delay to stay well under 40 req/s (Shopify limit is 2/s leaky bucket, max 40)
                usleep(500000);
            }

            // Refresh the storeOverride variants collection to reflect any ID updates
            $storeOverride->load('variants');
        } catch (\Throwable $e) {
            Log::error('Shopify: Variation reconciliation failed', [
                'product_id' => $shopifyProductId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function deleteVariant(string $productId, int|string $variantId, ?string $sku): void
    {
        Log::info('Shopify: Deleting orphaned variant', [
            'product_id' => $productId,
            'variant_id' => $variantId,
            'sku' => $sku,
        ]);

        $attempts = 0;
        $maxAttempts = 3;

        while ($attempts < $maxAttempts) {
            try {
                $response = $this->request('DELETE', "products/{$productId}/variants/{$variantId}.json");

                if ($response->successful()) {
                    return;
                }

                if ($response->status() === 429) {
                    $retryAfter = (float) $response->header('Retry-After', 1.0);
                    Log::warning("Shopify 429 Rate Limit Hit during variant deletion. Retrying after {$retryAfter}s.", ['variant_id' => $variantId]);
                    usleep((int) ($retryAfter * 1000000) + 500000); // Wait + buffer
                    $attempts++;

                    continue;
                }

                Log::error('Shopify: Failed to delete variant', [
                    'variant_id' => $variantId,
                    'status' => $response->status(),
                    'error' => $response->body(),
                ]);

                return; // Non-retriable error

            } catch (\Throwable $e) {
                Log::error('Shopify: Exception deleting variant', [
                    'variant_id' => $variantId,
                    'error' => $e->getMessage(),
                ]);

                return;
            }
        }

        Log::error('Shopify: Failed to delete variant after retries', ['variant_id' => $variantId]);
    }

    public function syncVariationBatch(string $shopifyProductId, VendorDesignTemplateStore $storeOverride, array $create, array $update, bool $reconcile = true): array
    {
        $store = $storeOverride->connectedStore;
        $this->ensureClient($store);

        // Ensure product images are up-to-date (includes new variant images)
        $this->ensureProductImages($shopifyProductId, $storeOverride);

        // Fetch current product images to map Position -> Image ID
        $imageMap = $this->getProductImagePositionMap($shopifyProductId);

        // Fetch all existing variants to prevent duplicate creation
        $existingVariants = $this->getAllVariants($shopifyProductId);
        $variantMap = []; // Key: OptionKey -> ID
        $skuMap = [];     // Key: SKU -> ID

        foreach ($existingVariants as $v) {
            $options = [
                $v['option1'] ?? null,
                $v['option2'] ?? null,
                $v['option3'] ?? null,
            ];
            // Filter out nulls and join
            $key = implode('|', array_filter($options, fn ($x) => ! is_null($x) && $x !== ''));
            if ($key) {
                $variantMap[$key] = $v['id'];
            }

            if (! empty($v['sku'])) {
                $skuMap[$v['sku']] = $v['id'];
            }
        }

        $errors = [];
        $total = 0;
        $success = 0;

        // Helper to process variant data
        $processVariant = function ($variantData, $isCreate) use (&$imageMap, &$total, &$success, &$errors, $shopifyProductId, $storeOverride, $variantMap, $skuMap) {
            $total++;

            $cost = null;
            if (isset($variantData['_cost'])) {
                $cost = $variantData['_cost'];
                unset($variantData['_cost']);
            }

            if (isset($variantData['_image_position'])) {
                $pos = $variantData['_image_position'];
                if (isset($imageMap[$pos])) {
                    $variantData['image_id'] = $imageMap[$pos];
                }
                unset($variantData['_image_position']);
            }

            // Check if it already exists (for create flow)
            if ($isCreate) {
                $options = [
                    $variantData['option1'] ?? null,
                    $variantData['option2'] ?? null,
                    $variantData['option3'] ?? null,
                ];
                $key = implode('|', array_filter($options, fn ($x) => ! is_null($x) && $x !== ''));
                $sku = $variantData['sku'] ?? '';

                $existingId = null;
                if ($key && isset($variantMap[$key])) {
                    $existingId = $variantMap[$key];
                } elseif ($sku && isset($skuMap[$sku])) {
                    $existingId = $skuMap[$sku];
                }

                if ($existingId) {
                    // Switch to Update
                    $variantData['id'] = $existingId;
                    $isCreate = false;

                    // Also link it locally immediately
                    $this->linkLocalVariant($storeOverride, ['id' => $existingId, 'sku' => $sku]);
                }
            }

            if ($isCreate) {
                $result = $this->createSingleVariant($shopifyProductId, $storeOverride, $variantData);
                if (is_string($result) && ! is_numeric($result)) {
                    $sku = $variantData['sku'] ?? 'unknown';
                    $errors[] = "New Variant (SKU $sku): $result";
                } else {
                    $success++;
                    $newVariantId = $result;
                    if ($newVariantId && $cost) {
                        $this->updateVariantCost($newVariantId, $cost);
                    }
                }
            } else {
                // Update
                if (! isset($variantData['id'])) {
                    // Should not happen if logic is correct
                    return;
                }

                $error = $this->updateSingleVariant($variantData, $storeOverride);
                if ($error) {
                    $errors[] = "Variant ID {$variantData['id']}: $error";
                } else {
                    $success++;
                    if ($cost) {
                        $this->updateVariantCost($variantData['id'], $cost);
                    }
                }
            }
        };

        // Process Updates
        foreach ($update as $variantData) {
            $processVariant($variantData, false);
        }

        // Process Creates
        foreach ($create as $variantData) {
            $processVariant($variantData, true);
        }

        // Reconcile Deletions (handle "same thing with delete")
        if ($reconcile) {
            $this->reconcileVariations($shopifyProductId, $storeOverride);
        }

        return [
            'total' => $total,
            'success' => $success,
            'errors' => $errors,
        ];
    }

    protected function getAllVariants(string $shopifyProductId): array
    {
        try {
            $response = $this->request('GET', "products/{$shopifyProductId}/variants.json", ['limit' => 250]);
            if ($response->successful()) {
                return $response->json('variants') ?? [];
            }
            // If request failed, throw exception to prevent assuming 0 variants
            throw new \Exception('Failed to fetch variants: '.$response->body());
        } catch (\Throwable $e) {
            Log::error('Shopify: Failed to fetch variants', ['error' => $e->getMessage()]);
            throw $e; // Re-throw to abort sync
        }
    }

    protected function ensureProductImages(string $shopifyProductId, VendorDesignTemplateStore $storeOverride): void
    {
        try {
            // Get expected images
            $expectedImages = $this->dataService->getOrderedImages($storeOverride);

            // Get current images
            $response = $this->request('GET', "products/{$shopifyProductId}/images.json");
            if ($response->failed()) {
                return;
            }

            $currentImages = $response->json('images') ?? [];

            // Map existing images by filename (or source URL) to preserve IDs
            // Shopify 'src' contains the full URL. We'll use basename of the path as key.
            // Note: Shopify sometimes adds version parameters to URLs, so parse_url is safer.
            $currentMap = [];
            foreach ($currentImages as $img) {
                $fname = basename(parse_url($img['src'], PHP_URL_PATH));
                $currentMap[$fname] = $img;
            }

            $imagePayload = [];
            $hasChanges = false;
            $processedFilenames = [];

            // 1. Add/Update Expected Images (Primary, Sync, Variant Designs)
            foreach ($expectedImages as $index => $imgData) {
                $fname = basename(parse_url($imgData['src'], PHP_URL_PATH));
                $processedFilenames[$fname] = true;

                $item = [
                    'position' => $index + 1,
                    'src' => $imgData['src'],
                ];

                if (isset($currentMap[$fname])) {
                    // Image exists, keep its ID
                    $item['id'] = $currentMap[$fname]['id'];

                    // Check if position changed
                    if ($currentMap[$fname]['position'] != ($index + 1)) {
                        $hasChanges = true;
                    }
                } else {
                    // New image
                    $hasChanges = true;
                }

                $imagePayload[] = $item;
            }

            // 2. Preserve ANY other existing images that are not in our expected list
            // This prevents deleting images that might be manually added or used by other variants
            // However, if we want to strictly sync (remove unused), we wouldn't do this.
            // But user said "images are remove for alreay there have images", implying unintentional deletion.
            // So we should append remaining existing images at the end.

            $nextPosition = count($imagePayload) + 1;
            foreach ($currentImages as $img) {
                $fname = basename(parse_url($img['src'], PHP_URL_PATH));

                if (! isset($processedFilenames[$fname])) {
                    // This image exists on Shopify but is not in our expected list.
                    // Keep it to be safe.
                    $imagePayload[] = [
                        'id' => $img['id'],
                        'src' => $img['src'],
                        'position' => $nextPosition++,
                    ];
                    // We technically changed the list structure, so we should flag update.
                    // But if we just re-send it, it's fine.
                    // If the expected list was empty and we just send back what we got, no change.
                }
            }

            // If we detected changes or if we want to be safe (since position check is simple), update.
            // To be robust: If count differs or if any expected image was new, we update.
            // If we only re-ordered, we also update.

            // Simple logic: Always update if we have expected images, to ensure order.
            // Or only if hasChanges is true.

            if ($hasChanges || count($expectedImages) > 0) {
                $this->request('PUT', "products/{$shopifyProductId}.json", [
                    'product' => [
                        'id' => $shopifyProductId,
                        'images' => $imagePayload,
                    ],
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('Shopify: Failed to ensure product images', ['error' => $e->getMessage()]);
        }
    }

    protected function updateVariantCost(string|int $variantId, string $cost): void
    {
        try {
            // 1. Get Inventory Item ID from Variant
            $response = $this->request('GET', "variants/{$variantId}.json");
            if ($response->successful()) {
                $variant = $response->json('variant');
                $inventoryItemId = $variant['inventory_item_id'] ?? null;

                if ($inventoryItemId) {
                    // 2. Update Inventory Item
                    $this->request('PUT', "inventory_items/{$inventoryItemId}.json", [
                        'inventory_item' => [
                            'id' => $inventoryItemId,
                            'cost' => $cost,
                        ],
                    ]);
                }
            }
        } catch (\Throwable $e) {
            Log::error('Shopify: Failed to update variant cost', ['variant_id' => $variantId, 'error' => $e->getMessage()]);
        }
    }

    protected function updateSingleVariant(array $variantData, VendorDesignTemplateStore $storeOverride): ?string
    {
        try {
            $response = $this->request('PUT', "variants/{$variantData['id']}.json", ['variant' => $variantData]);
            if ($response->failed()) {
                if ($response->status() === 404) {
                    // Variant does not exist on Shopify. Clear external ID.
                    Log::info("Shopify: Variant {$variantData['id']} not found (404), clearing external_variant_id.", [
                        'variant_id' => $variantData['id'],
                        'store_override_id' => $storeOverride->id,
                    ]);
                    $storeOverride->variants()
                        ->where('external_variant_id', $variantData['id'])
                        ->update(['external_variant_id' => null]);

                    return 'Variant not found (404) - cleared external ID';
                }

                $error = 'Shopify Variant Update Failed: '.$response->body();
                Log::error($error, ['id' => $variantData['id']]);

                return $error;
            }

            return null;
        } catch (\Throwable $e) {
            $error = 'Shopify Variant Update Exception: '.$e->getMessage();
            Log::error($error, ['id' => $variantData['id']]);

            return $error;
        }
    }

    protected function createSingleVariant(string $shopifyProductId, VendorDesignTemplateStore $storeOverride, array $variantData): string|int|null
    {
        try {
            $response = $this->request('POST', "products/{$shopifyProductId}/variants.json", ['variant' => $variantData]);
            if ($response->successful()) {
                $shopifyVariant = $response->json('variant');
                $this->linkLocalVariant($storeOverride, $shopifyVariant);

                return $shopifyVariant['id'] ?? null;
            } else {
                $error = 'Shopify Variant Create Failed: '.$response->body();
                Log::error($error, ['data' => $variantData]);

                return $error;
            }
        } catch (\Throwable $e) {
            $error = 'Shopify Variant Create Exception: '.$e->getMessage();
            Log::error($error, ['data' => $variantData]);

            return $error;
        }
    }

    /**
     * Fetch orders from Shopify with filtering and pagination.
     *
     * @param  string|null  $sinceDate  ISO 8601 date string (e.g., "2024-01-01T00:00:00Z")
     * @param  string|null  $financialStatus  Filter by financial status (e.g., 'paid', 'pending', 'authorized')
     * @param  int  $limit  Number of orders per page (max 250)
     * @return array Array of order payloads
     */
    public function fetchOrders(
        VendorConnectedStore $store,
        ?string $sinceDate = null,
        ?string $financialStatus = 'paid',
        int $limit = 250
    ): array {
        $this->ensureClient($store);

        // Validate credentials are set (consistency with getProductByExternalId)
        if (empty($this->accessToken) || empty($this->shopDomain)) {
            throw new \RuntimeException(
                "Shopify credentials not set for store {$store->id}. ".
                'Ensure store has valid access token and shop domain.'
            );
        }

        $allOrders = [];
        $params = [
            'limit' => min($limit, 250),
            'status' => 'any',
        ];

        if ($financialStatus) {
            $params['financial_status'] = $financialStatus;
        }

        if ($sinceDate) {
            $params['created_at_min'] = $sinceDate;
        }

        $pageInfo = null;
        $hasNextPage = true;
        $iteration = 0;
        $maxIterations = 100; // Safety limit

        try {
            while ($hasNextPage && $iteration < $maxIterations) {
                $iteration++;

                // Build query string
                if ($pageInfo) {
                    $queryParams = ['limit' => $params['limit'], 'page_info' => $pageInfo];
                } else {
                    $queryParams = $params;
                }

                $response = null;
                $maxRetries = 3;
                $retryAttempt = 0;

                // Handle rate limiting with exponential backoff and jitter
                while ($retryAttempt < $maxRetries) {
                    $response = $this->request('GET', 'orders.json', $queryParams);

                    // Check for HTTP 429 (rate limit)
                    if ($response->status() === 429) {
                        $retryAttempt++;

                        if ($retryAttempt < $maxRetries) {
                            // Exponential backoff with jitter
                            $retryAfter = (int) $response->header('Retry-After', 2);
                            $baseDelay = max($retryAfter, 2); // Use Retry-After or minimum 2s
                            $maxBackoff = 60; // Maximum 60 seconds
                            $exponentialDelay = min($baseDelay * (2 ** ($retryAttempt - 1)), $maxBackoff);
                            $jitter = rand(0, (int) ($exponentialDelay * 0.1)); // 10% jitter
                            $actualDelay = $exponentialDelay + $jitter;

                            Log::warning('Shopify rate limit hit, retrying with backoff', [
                                'store_id' => $store->id,
                                'attempt' => $retryAttempt,
                                'delay_seconds' => $actualDelay,
                                'retry_after_header' => $retryAfter,
                            ]);

                            sleep($actualDelay);

                            continue;
                        }
                    }

                    break; // Success or non-429 error
                }

                if ($response->failed()) {
                    Log::error('Failed to fetch Shopify orders', [
                        'status' => $response->status(),
                        'body' => $response->body(),
                        'store_id' => $store->id,
                        'attempts' => $retryAttempt + 1,
                    ]);
                    break;
                }

                $data = $response->json();
                $orders = $data['orders'] ?? [];

                if (empty($orders)) {
                    break;
                }

                // Use array_push with unpacking instead of array_merge for performance
                array_push($allOrders, ...$orders);

                // Extract pagination info
                $linkHeader = $response->header('Link');
                $pageInfo = $this->extractPageInfo($linkHeader);

                if (! $pageInfo) {
                    $hasNextPage = false;
                }

                // Rate limiting: Shopify allows 2 requests per second
                if ($hasNextPage) {
                    usleep(500000); // 0.5 seconds
                }
            }

            // Warn if we hit the safety limit
            if ($iteration >= $maxIterations) {
                Log::warning('Shopify order fetch hit safety limit', [
                    'store_id' => $store->id,
                    'max_iterations' => $maxIterations,
                    'orders_returned' => count($allOrders),
                    'estimated_total' => $maxIterations * $limit,
                    'since_date' => $sinceDate,
                    'financial_status' => $financialStatus,
                    'message' => 'Safety limit reached - only first ~'.($maxIterations * $limit).' orders returned',
                ]);
            }

            Log::info('Fetched Shopify orders', [
                'store_id' => $store->id,
                'count' => count($allOrders),
                'iterations' => $iteration,
            ]);

            return $allOrders;
        } catch (\Throwable $e) {
            Log::error('Exception fetching Shopify orders', [
                'store_id' => $store->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Extract page_info cursor from Shopify Link header.
     *
     * @param  string|null  $linkHeader  The Link header value
     * @return string|null The page_info cursor or null
     */
    protected function extractPageInfo(?string $linkHeader): ?string
    {
        if ($linkHeader === null) {
            return null;
        }

        // Split on commas to get individual links
        $links = explode(',', $linkHeader);

        foreach ($links as $link) {
            // Check if this link has rel="next" or rel='next'
            if (preg_match('/rel=["\']next["\']/', $link)) {
                // Extract URL from angle brackets
                if (preg_match('/<([^>]+)>/', $link, $matches)) {
                    $url = $matches[1];

                    // Parse query string to get page_info
                    $queryString = parse_url($url, PHP_URL_QUERY);
                    if ($queryString) {
                        parse_str($queryString, $params);

                        return $params['page_info'] ?? null;
                    }
                }
            }
        }

        return null;
    }

    protected function linkLocalVariant(VendorDesignTemplateStore $storeOverride, array $shopifyVariant): void
    {
        $sku = $shopifyVariant['sku'] ?? null;
        if ($sku) {
            $localVariant = $storeOverride->variants()->where('sku', $sku)->first();
            if ($localVariant) {
                $localVariant->update(['external_variant_id' => $shopifyVariant['id']]);
            }
        }
    }

    protected function ensureClient(mixed $store): void
    {
        if (! $this->accessToken && $store && $store->token) {
            try {
                $tokenData = decrypt($store->token);
                $this->accessToken = $tokenData['access_token'] ?? null;
                $this->shopDomain = $tokenData['shop'] ?? null;

                if (! $this->shopDomain && $store->link) {
                    $link = $store->link;
                    if ($link && ! preg_match('#^https?://#i', $link)) {
                        $link = 'https://'.$link;
                    }
                    $host = parse_url($link, PHP_URL_HOST);
                    if (! $host) {
                        $host = preg_replace('#^https?://#i', '', $link);
                        $host = rtrim($host, '/');
                    }
                    $this->shopDomain = $host ?: null;
                }
            } catch (\Throwable $e) {
                $raw = $store->token;
                $decoded = null;
                if (is_string($raw)) {
                    $trim = ltrim($raw);
                    if (str_starts_with($trim, '{') && str_ends_with(rtrim($trim), '}')) {
                        try {
                            $decoded = json_decode($raw, true);
                        } catch (\Throwable $jsonE) {
                            $decoded = null;
                        }
                    }
                }
                if (is_array($decoded)) {
                    $this->accessToken = $decoded['access_token'] ?? null;
                    $this->shopDomain = $decoded['shop'] ?? null;
                    if (! $this->shopDomain && $store->link) {
                        $link = $store->link;
                        if ($link && ! preg_match('#^https?://#i', $link)) {
                            $link = 'https://'.$link;
                        }
                        $host = parse_url($link, PHP_URL_HOST);
                        if (! $host) {
                            $host = preg_replace('#^https?://#i', '', $link);
                            $host = rtrim($host, '/');
                        }
                        $this->shopDomain = $host ?: null;
                    }

                    return;
                }
                if (is_string($raw) && str_starts_with($raw, 'shpat_')) {
                    $this->accessToken = $raw;
                    if (! $this->shopDomain && $store->link) {
                        $link = $store->link;
                        if ($link && ! preg_match('#^https?://#i', $link)) {
                            $link = 'https://'.$link;
                        }
                        $host = parse_url($link, PHP_URL_HOST);
                        if (! $host) {
                            $host = preg_replace('#^https?://#i', '', $link);
                            $host = rtrim($host, '/');
                        }
                        $this->shopDomain = $host ?: null;
                    }

                    return;
                }
                Log::warning('Shopify: invalid or unreadable store credentials', ['store_id' => $store->id ?? 'unknown']);
                try {
                    $store->status = 'error';
                    $store->error_message = 'Invalid store token; please reconnect your Shopify store.';
                    $store->save();
                } catch (\Throwable $persistE) {
                    // ignore persist errors
                }

                return;
            }
        }
    }

    protected function request(string $method, string $endpoint, array $data = []): Response
    {
        if (! $this->accessToken || ! $this->shopDomain) {
            throw new \RuntimeException('Access Token or Shop Domain missing for Shopify Request');
        }

        $apiVersion = config('services.shopify.api_version', '2024-01');
        $url = "https://{$this->shopDomain}/admin/api/{$apiVersion}/{$endpoint}";
        $method = strtolower($method);

        return Http::withHeaders([
            'X-Shopify-Access-Token' => $this->accessToken,
            'Content-Type' => 'application/json',
        ])
            ->connectTimeout(self::TIMEOUT_CONNECT)
            ->timeout(self::TIMEOUT_REQUEST)
            ->$method($url, $data);
    }

    protected function getProductImagePositionMap(string $productId): array
    {
        try {
            $response = $this->request('GET', "products/{$productId}/images.json");

            if ($response->successful()) {
                $images = $response->json('images') ?? [];
                $map = [];

                foreach ($images as $img) {
                    if (isset($img['position'], $img['id'])) {
                        $map[$img['position']] = $img['id'];
                    }
                }

                return $map;
            }
        } catch (\Throwable $e) {
            Log::error('Shopify: Failed to fetch images for mapping', [
                'product_id' => $productId,
                'error' => $e->getMessage(),
            ]);
        }

        return [];
    }

    protected function ensureFulfillmentServiceRegistered(VendorConnectedStore $store): void
    {
        $data = $store->additional_data ?? [];
        if (empty($data['fulfillment_service_id']) || empty($data['location_id']) || empty($data['fulfillment_service_handle'])) {
            try {
                if (! $this->accessToken || ! $this->shopDomain) {
                    $this->ensureClient($store);
                }

                $service = app(ShopifyFulfillmentService::class);
                $result = $service->register($this->shopDomain, $this->accessToken);

                if (isset($result['service_id'], $result['location_id']) && $result['service_id'] && $result['location_id']) {
                    $data['fulfillment_service_id'] = $result['service_id'];
                    $data['location_id'] = $result['location_id'];
                    $data['fulfillment_service_handle'] = $result['handle'] ?? ShopifyFulfillmentService::SERVICE_HANDLE;
                    $store->additional_data = $data;
                    $store->save();
                } else {
                    Log::error('Shopify: Fulfillment service registration returned incomplete data', [
                        'store_id' => $store->id,
                        'result' => $result,
                    ]);
                }
            } catch (\Throwable $e) {
                Log::error('Shopify: Failed to ensure fulfillment service registration', [
                    'store_id' => $store->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    protected function publishToAllMarkets(string $externalProductId): void
    {
        try {
            // 1. Get all publications with pagination
            $query = <<<'GRAPHQL'
query($after: String) {
  publications(first: 50, after: $after) {
    edges {
      node {
        id
        name
      }
    }
    pageInfo {
      hasNextPage
      endCursor
    }
  }
}
GRAPHQL;

            $publications = [];
            $cursor = null;
            $hasNextPage = true;

            do {
                $response = $this->request('POST', 'graphql.json', [
                    'query' => $query,
                    'variables' => ['after' => $cursor],
                ]);

                if ($response->failed()) {
                    Log::error('Shopify: Failed to fetch publications', ['error' => $response->body()]);

                    return;
                }

                $data = $response->json();
                $connection = $data['data']['publications'] ?? [];
                $edges = $connection['edges'] ?? [];

                foreach ($edges as $edge) {
                    $publications[] = $edge;
                }

                $pageInfo = $connection['pageInfo'] ?? [];
                $hasNextPage = $pageInfo['hasNextPage'] ?? false;
                $cursor = $pageInfo['endCursor'] ?? null;

            } while ($hasNextPage);

            if (empty($publications)) {
                return;
            }

            $productGid = "gid://shopify/Product/{$externalProductId}";
            $publishInput = [];

            foreach ($publications as $edge) {
                $pubId = $edge['node']['id'] ?? null;
                if ($pubId) {
                    $publishInput[] = ['publicationId' => $pubId];
                }
            }

            if (empty($publishInput)) {
                return;
            }

            // 2. Publish to all publications
            $mutation = <<<'GRAPHQL'
mutation publishablePublish($id: ID!, $input: [PublicationInput!]!) {
  publishablePublish(id: $id, input: $input) {
    userErrors {
      field
      message
    }
    publishable {
      availablePublicationsCount {
        count
      }
    }
  }
}
GRAPHQL;

            /** @var \Illuminate\Http\Client\Response $mutationResponse */
            $mutationResponse = $this->request('POST', 'graphql.json', [
                'query' => $mutation,
                'variables' => [
                    'id' => $productGid,
                    'input' => $publishInput,
                ],
            ]);

            if ($mutationResponse->failed()) {
                Log::error('Shopify: Failed to publish product to markets', ['error' => $mutationResponse->body()]);
            } else {
                $mutationData = $mutationResponse->json();
                $userErrors = $mutationData['data']['publishablePublish']['userErrors'] ?? [];
                if (! empty($userErrors)) {
                    Log::warning('Shopify: User errors during market publishing', ['errors' => $userErrors, 'product_id' => $externalProductId]);
                }
            }
        } catch (\Throwable $e) {
            Log::error('Shopify: Exception publishing to markets', [
                'product_id' => $externalProductId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
