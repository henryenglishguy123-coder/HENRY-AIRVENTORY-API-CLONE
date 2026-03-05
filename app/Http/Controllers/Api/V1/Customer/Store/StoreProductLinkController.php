<?php

namespace App\Http\Controllers\Api\V1\Customer\Store;

use App\Http\Controllers\Api\V1\Customer\Account\AccountController;
use App\Http\Controllers\Controller;
use App\Models\Customer\Designer\VendorDesignTemplateCatalogProduct;
use App\Models\Customer\Designer\VendorDesignTemplateStore;
use App\Models\Customer\Store\VendorConnectedStore;
use App\Services\Channels\Factory\StoreConnectorFactory;
use App\Services\Channels\Shopify\ShopifyConnector;
use App\Services\Channels\WooCommerce\WooCommerceConnector;
use App\Services\Customer\Template\VendorDesignTemplateStoreService;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StoreProductLinkController extends Controller
{
    public function __construct(
        private StoreConnectorFactory $connectorFactory
    ) {}

    public function linkExistingProduct(Request $request): JsonResponse
    {
        $customer = app(AccountController::class)->resolveCustomer($request);
        if (! $customer) {
            return response()->json([
                'success' => false,
                'message' => __('Unauthenticated.'),
            ], Response::HTTP_UNAUTHORIZED);
        }

        $validated = $request->validate([
            'store_id' => ['required', 'integer', \Illuminate\Validation\Rule::exists('vendor_connected_stores', 'id')->where('vendor_id', $customer->id)],
            'name' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'sync_images' => ['nullable', 'array'],
            'external_product_id' => ['required'],
            'product_id' => ['required', 'integer', \Illuminate\Validation\Rule::exists('catalog_products', 'id')],
            'template_id' => ['required', 'integer', \Illuminate\Validation\Rule::exists('vendor_design_templates', 'id')->where('vendor_id', $customer->id)],
            'variants' => ['required', 'array', 'min:1'],
            'variants.*.product_variant_id' => ['required', 'integer', \Illuminate\Validation\Rule::exists('catalog_products', 'id')],
            'variants.*.external_variant_id' => ['required'],
        ]);

        $store = VendorConnectedStore::query()
            ->where('vendor_id', $customer->id)
            ->where('id', $validated['store_id'])
            ->first();
        if (! $store) {
            return response()->json([
                'success' => false,
                'message' => __('Store not found.'),
            ], Response::HTTP_NOT_FOUND);
        }

        // Check if design template exists for the product
        $templateLink = VendorDesignTemplateCatalogProduct::query()
            ->where('vendor_id', $customer->id)
            ->where('catalog_product_id', (int) $validated['product_id'])
            ->where('vendor_design_template_id', (int) $validated['template_id'])
            ->first();

        if (! $templateLink) {
            return response()->json([
                'success' => false,
                'message' => __('No design template found for the selected product. Please create or select a design template first.'),
            ], Response::HTTP_PRECONDITION_FAILED);
        }

        // Check if already linked
        $normalizedProductId = (string) $validated['external_product_id'];
        if ($store->channel === 'shopify' && str_starts_with($normalizedProductId, 'gid://shopify/Product/')) {
            $normalizedProductId = (string) preg_replace('#^gid://shopify/Product/#', '', $normalizedProductId);
        }
        $existing = VendorDesignTemplateStore::query()
            ->where('vendor_connected_store_id', $store->id)
            ->where('external_product_id', $normalizedProductId)
            ->first();

        // If existing link found, ensure it belongs to the same template (idempotent update)
        // If it belongs to a DIFFERENT template, then it's a conflict.
        if ($existing && $existing->vendor_design_template_id !== $templateLink->vendor_design_template_id) {
            return response()->json([
                'success' => false,
                'message' => __('Product already linked for this store.'),
                'data' => [
                    'store_override_id' => $existing->id,
                    'vendor_design_template_id' => $existing->vendor_design_template_id,
                ],
            ], Response::HTTP_CONFLICT);
        }

        // Verify product exists and all provided variants exist in the store
        $connector = $this->connectorFactory->make($store->storeChannel);
        try {
            $externalProduct = $connector->getProductByExternalId($store, (string) $validated['external_product_id']);
        } catch (\Throwable $e) {
            Log::error('Product link verification failed during product fetch', [
                'store_id' => $store->id,
                'channel' => $store->channel,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => __('Failed to verify product from store. Please check your connection and try again.'),
            ], Response::HTTP_BAD_GATEWAY);
        }
        if (! $externalProduct) {
            return response()->json([
                'success' => false,
                'message' => __('External product not found in store.'),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            [$availableVariantIds, $variantSkuMap, $productSku] = $this->collectVariantData(
                $store,
                $connector,
                (string) $validated['external_product_id'],
                $externalProduct
            );
        } catch (\Throwable $e) {
            Log::error('Variant verification failed during collection', [
                'store_id' => $store->id,
                'channel' => $store->channel,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => __('Failed to verify product variants from store. Please check your connection and try again.'),
            ], Response::HTTP_BAD_GATEWAY);
        }

        // Normalize Shopify variant IDs
        if ($store->channel === 'shopify') {
            foreach ($validated['variants'] as &$variant) {
                $variant['external_variant_id'] = (string) preg_replace('#^gid://shopify/ProductVariant/#', '', (string) $variant['external_variant_id']);
            }
            unset($variant); // break reference
        }

        $providedVariantIds = collect($validated['variants'])
            ->pluck('external_variant_id')
            ->map(fn ($id) => (string) $id)
            ->unique()
            ->values()
            ->all();
        $missingVariantIds = array_values(array_diff($providedVariantIds, $availableVariantIds));
        if (! empty($missingVariantIds)) {
            return response()->json([
                'success' => false,
                'message' => __('One or more provided variants do not exist in the store product.'),
                'data' => [
                    'missing_variant_ids' => $missingVariantIds,
                ],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // $templateLink is already fetched above

        $service = app(VendorDesignTemplateStoreService::class);

        $variants = array_map(function ($v) {
            // Ensure external_variant_id is string
            return [
                'catalog_product_id' => (int) $v['product_variant_id'],
                'external_variant_id' => (string) $v['external_variant_id'],
                'is_enabled' => true,
            ];
        }, $validated['variants']);

        $data = [
            'store_id' => (int) $validated['store_id'],
            'name' => $validated['name'] ?? null,
            'description' => $validated['description'] ?? null,
            'sync_images' => $validated['sync_images'] ?? [],
            'variants' => $variants,
            'status' => 'active',
        ];

        $result = null;

        try {
            $result = DB::transaction(function () use ($service, $templateLink, $data, $customer, $normalizedProductId, $productSku, $variantSkuMap, $store, $providedVariantIds, $connector) {
                $template = \App\Models\Customer\Designer\VendorDesignTemplate::findOrFail($templateLink->vendor_design_template_id);
                $storeOverride = $service->updateStoreSettings($template, $data, $customer->id, false);

                $storeOverride->update([
                    'external_product_id' => $normalizedProductId,
                    'sync_status' => 'synced',
                    'sync_error' => null,
                    'is_link_only' => true,
                ]);

                $this->updateSkus($storeOverride, $productSku, $variantSkuMap);

                // Fulfillment-only: for Shopify, assign our fulfillment service to the linked variants
                if ($store->channel === 'shopify' && isset($connector) && $connector instanceof ShopifyConnector) {
                    try {
                        $connector->assignFulfillmentServiceToExistingProduct(
                            $store,
                            $normalizedProductId,
                            $providedVariantIds
                        );
                    } catch (\Throwable $e) {
                        Log::warning('Shopify fulfillment assignment failed after linking. Rolling back.', [
                            'store_id' => $store->id,
                            'product_id' => $normalizedProductId,
                            'error' => $e->getMessage(),
                        ]);

                        throw $e; // Re-throw to trigger rollback
                    }
                }

                return $storeOverride;
            });

            return response()->json([
                'success' => true,
                'message' => __('Product linked successfully.'),
                'data' => [
                    'store_override_id' => $result->id,
                    'external_product_id' => $result->external_product_id,
                    'variant_count' => $result->variants()->count(),
                ],
            ], Response::HTTP_OK);
        } catch (QueryException $e) {
            $message = $e->getMessage();
            $isDuplicate = str_contains($message, 'vendor_store_external_product_unique')
                || $e->getCode() === '23000'
                || $e->errorInfo[1] ?? null === 1062;
            if ($isDuplicate) {
                $existing = VendorDesignTemplateStore::query()
                    ->where('vendor_connected_store_id', $store->id)
                    ->where('external_product_id', $normalizedProductId)
                    ->first();

                return response()->json([
                    'success' => false,
                    'message' => __('Product already linked for this store.'),
                    'data' => [
                        'store_override_id' => $existing?->id,
                        'vendor_design_template_id' => $existing?->vendor_design_template_id,
                    ],
                ], Response::HTTP_CONFLICT);
            }
            Log::error('Database error during product link', [
                'vendor_id' => $customer->id,
                'store_id' => $store->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => __('Failed to link product. Please try again.'),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        } catch (\Throwable $e) {
            Log::error('Failed to link existing store product', [
                'vendor_id' => $customer->id,
                'store_id' => $store->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => __('Failed to link product. Please try again.'),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    private function collectVariantData(
        VendorConnectedStore $store,
        $connector,
        string $externalProductId,
        array $externalProduct
    ): array {
        $availableVariantIds = [];
        $variantSkuMap = [];
        $productSku = null;
        if ($store->channel === 'shopify') {
            $variants = collect($externalProduct['variants'] ?? []);
            $availableVariantIds = $variants
                ->pluck('id')
                ->filter()
                ->map(fn ($id) => (string) $id)
                ->values()
                ->all();
            $variantSkuMap = $variants
                ->filter(fn ($v) => isset($v['id']))
                ->mapWithKeys(fn ($v) => [
                    (string) $v['id'] => isset($v['sku']) ? (string) $v['sku'] : '',
                ])
                ->all();
        } elseif ($store->channel === 'woocommerce' && $connector instanceof WooCommerceConnector) {
            [$availableVariantIds, $variantSkuMap, $productSku] = $this->getWooVariantData(
                $connector,
                $store,
                $externalProductId,
                $externalProduct
            );
        }

        return [$availableVariantIds, $variantSkuMap, $productSku];
    }

    private function updateSkus(
        VendorDesignTemplateStore $storeOverride,
        ?string $productSku,
        array $variantSkuMap
    ): void {
        if (\is_string($productSku) && $productSku !== '') {
            $storeOverride->update([
                'sku' => mb_substr($productSku, 0, 191),
            ]);
        }
        $storeOverride->loadMissing('variants');
        foreach ($storeOverride->variants as $storeVar) {
            $extId = (string) ($storeVar->external_variant_id ?? '');
            if ($extId === '' || ! \array_key_exists($extId, $variantSkuMap)) {
                continue;
            }
            $skuVal = (string) $variantSkuMap[$extId];
            if ($skuVal === '') {
                continue;
            }
            $storeVar->update([
                'sku' => mb_substr($skuVal, 0, 191),
            ]);
        }
    }

    private function getWooVariantData(
        WooCommerceConnector $wooConnector,
        VendorConnectedStore $store,
        string $externalProductId,
        array $externalProduct
    ): array {
        $wooVariations = $wooConnector->getVariationsByProductId($store, $externalProductId);
        $availableVariantIds = collect($wooVariations)
            ->pluck('id')
            ->filter()
            ->map(fn ($id) => (string) $id)
            ->values()
            ->all();
        $variantSkuMap = collect($wooVariations)
            ->filter(fn ($v) => isset($v['id']))
            ->mapWithKeys(fn ($v) => [
                (string) $v['id'] => isset($v['sku']) ? (string) $v['sku'] : '',
            ])
            ->all();
        $productSku = isset($externalProduct['sku']) ? (string) $externalProduct['sku'] : null;

        return [$availableVariantIds, $variantSkuMap, $productSku];
    }
}
