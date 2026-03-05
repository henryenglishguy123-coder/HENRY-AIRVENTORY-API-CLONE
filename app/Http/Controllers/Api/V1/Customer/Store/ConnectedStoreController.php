<?php

namespace App\Http\Controllers\Api\V1\Customer\Store;

use App\Http\Controllers\Api\V1\Customer\Account\AccountController;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Customer\ConnectedStoreResource;
use App\Http\Resources\Api\V1\Customer\ExternalProductResource;
use App\Jobs\WooCommerce\DeleteWooCommerceWebhooksJob;
use App\Models\Customer\Designer\VendorDesignTemplateStore;
use App\Models\Customer\Store\VendorConnectedStore;
use App\Services\Channels\Factory\StoreConnectorFactory;
use App\Services\Channels\Transformers\ProductNormalizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class ConnectedStoreController extends Controller
{
    public function __construct(
        private StoreConnectorFactory $connectorFactory
    ) {}

    public function productLookup(Request $request): JsonResponse
    {
        $customer = app(AccountController::class)->resolveCustomer($request);
        if (! $customer) {
            return response()->json([
                'success' => false,
                'message' => __('Unauthenticated.'),
            ], Response::HTTP_UNAUTHORIZED);
        }
        $validated = $request->validate([
            'product_id' => ['required', 'string', 'max:255'],
            'store_id' => ['required_without:store_identifier', 'integer'],
            'store_identifier' => ['required_without:store_id', 'string', 'max:255'],
        ]);
        $storeQuery = VendorConnectedStore::query()->where('vendor_id', $customer->id);
        $store = ! empty($validated['store_id'] ?? null)
            ? $storeQuery->where('id', (int) $validated['store_id'])->first()
            : $storeQuery->where('store_identifier', $validated['store_identifier'])->first();
        if (! $store) {
            return response()->json([
                'success' => false,
                'message' => __('Store not found.'),
            ], Response::HTTP_NOT_FOUND);
        }
        $rawProductId = (string) $validated['product_id'];
        $normalizedProductId = $rawProductId;
        if ($store->channel === 'shopify' && str_starts_with($rawProductId, 'gid://shopify/Product/')) {
            $normalizedProductId = (string) preg_replace('#^gid://shopify/Product/#', '', $rawProductId);
        }
        $existing = VendorDesignTemplateStore::query()
            ->where('vendor_connected_store_id', $store->id)
            ->where('external_product_id', $normalizedProductId)
            ->first();
        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => __('Product already synced with a design template for this store.'),
                'data' => [
                    'store_override_id' => $existing->id,
                    'vendor_design_template_id' => $existing->vendor_design_template_id,
                ],
            ], Response::HTTP_CONFLICT);
        }
        $connector = $this->connectorFactory->make($store->storeChannel);
        try {
            $externalProduct = $connector->getProductByExternalId($store, (string) $validated['product_id']);
        } catch (\RuntimeException $e) {
            Log::error('Product lookup failed due to connector error', [
                'store_id' => $store->id,
                'channel' => $store->channel,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => __('Failed to fetch product from store. Please verify your connection and try again.'),
            ], Response::HTTP_BAD_GATEWAY);
        } catch (\Throwable $e) {
            Log::error('Product lookup unexpected exception', [
                'store_id' => $store->id,
                'channel' => $store->channel,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => __('An unexpected error occurred during product lookup.'),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
        $wooVariations = null;
        if ($store->channel === 'woocommerce' && $externalProduct) {
            if ($connector instanceof \App\Services\Channels\WooCommerce\WooCommerceConnector) {
                $wooVariations = $connector->getVariationsByProductId($store, (string) $validated['product_id']);
            }
        }
        if (! $externalProduct) {
            return response()->json([
                'success' => false,
                'message' => __('Product not found for this store.'),
            ], Response::HTTP_OK);
        }
        $response = [
            'success' => true,
            'data' => [
                'store' => [
                    'id' => $store->id,
                    'channel' => $store->channel,
                    'domain' => $store->link ? preg_replace('#^https?://#', '', $store->link) : null,
                ],
                'product' => ExternalProductResource::make(
                    $this->transformExternalProduct($externalProduct, $store->channel, $wooVariations)
                ),
            ],
        ];

        return response()->json($response, Response::HTTP_OK);
    }

    protected function transformExternalProduct(array $data, string $channel, ?array $wooVariations = null): array
    {
        if ($channel === 'shopify') {
            return ProductNormalizer::shopify($data);
        }
        if ($channel === 'woocommerce') {
            return ProductNormalizer::woocommerce($data, $wooVariations);
        }
        throw new \InvalidArgumentException("Unsupported channel '{$channel}' for product normalization.");
    }

    public function disconnect(Request $request, int $id): JsonResponse
    {
        $customer = app(AccountController::class)->resolveCustomer($request);

        if (! $customer) {
            return response()->json([
                'success' => false,
                'message' => __('You must be logged in to perform this action.'),
            ], Response::HTTP_UNAUTHORIZED);
        }

        $store = VendorConnectedStore::where('vendor_id', $customer->id)
            ->where('id', $id)
            ->firstOrFail();

        // Mark store as disconnected / disabled
        $store->markDisconnected(__('Store disconnected by customer'));

        // If it's a WooCommerce store, attempt to remove webhooks
        if ($store->channel === 'woocommerce') {
            DeleteWooCommerceWebhooksJob::dispatch($store->id);
        }

        return response()->json([
            'success' => true,
            'message' => __(
                'The store has been disconnected successfully. Syncing and automated updates are now disabled for this store.'
            ),
            'data' => [
                'store_id' => $store->id,
                'status' => 'disconnected',
                'enabled' => false,
            ],
        ], Response::HTTP_OK);
    }

    /**
     * Check connection status for a specific store
     */
    public function checkConnection(Request $request, int $id): JsonResponse
    {
        $customer = app(AccountController::class)->resolveCustomer($request);

        if (! $customer) {
            return response()->json([
                'success' => false,
                'message' => __('Unauthenticated.'),
            ], Response::HTTP_UNAUTHORIZED);
        }

        $store = VendorConnectedStore::where('vendor_id', $customer->id)
            ->where('id', $id)
            ->firstOrFail();

        try {
            $connector = $this->connectorFactory->make($store->storeChannel);

            // Decrypt credentials based on what the connector expects
            try {
                $decryptedToken = decrypt($store->token);
            } catch (\Illuminate\Contracts\Encryption\DecryptException $e) {
                // If decryption fails, use the raw token (e.g. for Shopify which might store plain tokens)
                $decryptedToken = $store->token;
            }

            $credentials = [
                'link' => $store->link,
            ];

            if (is_array($decryptedToken)) {
                $credentials['consumer_key'] = $decryptedToken['consumer_key'] ?? null;
                $credentials['consumer_secret'] = $decryptedToken['consumer_secret'] ?? null;
                $credentials['access_token'] = $decryptedToken['access_token'] ?? null;
            } else {
                $credentials['access_token'] = $decryptedToken;
                $credentials['consumer_key'] = null;
                $credentials['consumer_secret'] = null;
            }

            if ($connector->verify($credentials)) {
                $store->markConnected();

                return response()->json([
                    'success' => true,
                    'message' => __('Store connection is working properly.'),
                    'data' => [
                        'status' => 'connected',
                    ],
                ], Response::HTTP_OK);
            } else {
                $store->markError(__('Unable to verify store credentials.'));

                return response()->json([
                    'success' => false,
                    'message' => __('Store connection failed. Please reconnect your store.'),
                    'data' => [
                        'status' => 'error',
                        'error' => __('Invalid credentials or store unreachable.'),
                    ],
                ], Response::HTTP_OK);
            }
        } catch (\Throwable $e) {
            Log::error('Store connection check failed', [
                'store_id' => $store->id,
                'vendor_id' => $customer->id,
                'exception' => $e,
            ]);
            $store->markError('Connection check failed');

            return response()->json([
                'success' => false,
                'message' => __('An error occurred while checking connection.'),
                'data' => [
                    'status' => 'error',
                ],
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * List connected stores for authenticated customer
     */
    public function index(Request $request): JsonResponse
    {
        $customer = app(AccountController::class)->resolveCustomer($request);

        if (! $customer) {
            return response()->json([
                'success' => false,
                'message' => __('Unauthenticated.'),
            ], Response::HTTP_UNAUTHORIZED);
        }

        $validated = $request->validate([
            'channel' => ['nullable', 'string', 'max:50'],
            'status' => ['nullable', 'in:connected,disconnected,error'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
            'page' => ['nullable', 'integer', 'min:1'],
            'sort' => ['nullable', 'in:latest,oldest'],
        ]);

        $limit = $validated['limit'] ?? 10;
        $page = $validated['page'] ?? 1;
        $sort = $validated['sort'] ?? 'latest';

        $query = VendorConnectedStore::query()
            ->where('vendor_id', $customer->id)
            ->when(
                $validated['channel'] ?? null,
                fn ($q, $channel) => $q->where('channel', $channel)
            )
            ->when(
                $validated['status'] ?? null,
                fn ($q, $status) => $q->where('status', $status)
            )
            ->when(
                $sort === 'oldest',
                fn ($q) => $q->oldest(),
                fn ($q) => $q->latest()
            );

        $total = $query->count();

        $stores = $query
            ->skip(($page - 1) * $limit)
            ->take($limit)
            ->get();

        return response()->json([
            'success' => true,
            'data' => ConnectedStoreResource::collection($stores),
            'meta' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'has_more' => ($page * $limit) < $total,
                'filters' => [
                    'channel' => $validated['channel'] ?? null,
                    'status' => $validated['status'] ?? null,
                ],
            ],
            'message' => __('Connected stores retrieved successfully.'),
        ], Response::HTTP_OK);
    }
}
