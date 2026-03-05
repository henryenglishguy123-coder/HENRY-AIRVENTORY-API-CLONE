<?php

namespace App\Services\Customer\Template;

use App\Models\Customer\Designer\VendorDesignTemplate;
use App\Models\Customer\Designer\VendorDesignTemplateStore;
use App\Models\Customer\Designer\VendorDesignTemplateStoreImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class VendorDesignTemplateStoreService
{
    public function updateStoreSettings(VendorDesignTemplate $template, array $data, int $vendorId, bool $sync = true): VendorDesignTemplateStore
    {
        $storeOverride = DB::transaction(function () use ($template, $data, $vendorId, $sync) {
            $storeOverride = $this->updateOrCreateStoreOverride($template, $data, $vendorId);

            // Allow clearing variants
            if (isset($data['variants'])) {
                $this->handleVariants($storeOverride, $data['variants']);
            }

            if (! empty($data['primary_image'])) {
                $this->handlePrimaryImage($storeOverride, $data['primary_image']);
            }

            if (isset($data['sync_images'])) {
                $this->handleSyncImages($storeOverride, $data['sync_images']);
            }

            // If this store override represents a linked existing product, never queue syncs
            $linkOnly = (bool) ($storeOverride->is_link_only ?? false);

            if ($sync && ! $linkOnly) {
                $storeOverride->update([
                    'sync_status' => 'pending',
                    'sync_error' => null,
                ]);
            }

            return $storeOverride;
        });

        // Skip dispatch when sync not requested or link-only mode is set on the override
        if (! $sync || (bool) ($storeOverride->is_link_only ?? false)) {
            return $storeOverride;
        }

        try {
            $storeOverride->loadMissing('connectedStore.storeChannel');

            $channelCode = $storeOverride->connectedStore?->storeChannel?->code;

            $job = match ($channelCode) {
                'woocommerce' => new \App\Jobs\WooCommerce\SyncWooBaseProductJob($storeOverride),
                'shopify' => new \App\Jobs\Shopify\SyncShopifyBaseProductJob($storeOverride),
                default => null,
            };

            if (! $job) {
                $status = 'skipped';
                Log::warning('Sync skipped: unsupported channel', [
                    'store_override_id' => $storeOverride->id,
                    'channel' => $channelCode,
                    'status' => $status,
                ]);

                $storeOverride->update([
                    'sync_status' => $status,
                    'sync_error' => 'Unsupported channel: '.($channelCode ?? 'none'),
                ]);

                return $storeOverride;
            }

            dispatch($job)->afterCommit();

            Log::info('Sync job dispatched', [
                'channel' => $channelCode,
                'job_class' => get_class($job),
                'store_override_id' => $storeOverride->id,
                'template_id' => $template->id,
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to dispatch sync job', [
                'store_override_id' => $storeOverride->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $storeOverride->update([
                'sync_status' => 'failed',
                'sync_error' => 'Failed to initiate sync: '.$e->getMessage(),
            ]);
        }

        return $storeOverride;
    }

    public function deleteImage(VendorDesignTemplateStoreImage $image): bool
    {
        return $image->delete();
    }

    public function uploadImage(UploadedFile $file, int $storeId): string
    {
        $extension = $file->getClientOriginalExtension();
        $filename = $this->generateFilename($storeId, $extension);
        $targetDir = "vendor_design_template_stores/{$storeId}/images";

        return $file->storeAs($targetDir, $filename);
    }

    protected function updateOrCreateStoreOverride(VendorDesignTemplate $template, array $data, int $vendorId): VendorDesignTemplateStore
    {
        $updateData = [
            'name' => $data['name'] ?? null,
            'description' => $data['description'] ?? null,
        ];

        if (array_key_exists('hang_tag_id', $data)) {
            $updateData['hang_tag_id'] = $data['hang_tag_id'];
        }

        if (array_key_exists('packaging_label_id', $data)) {
            $updateData['packaging_label_id'] = $data['packaging_label_id'];
        }

        if (isset($data['status'])) {
            $updateData['status'] = $data['status'];
        }
        if (array_key_exists('is_link_only', $data)) {
            $updateData['is_link_only'] = (bool) $data['is_link_only'];
        }

        return VendorDesignTemplateStore::updateOrCreate(
            [
                'vendor_id' => $vendorId,
                'vendor_design_template_id' => $template->id,
                'vendor_connected_store_id' => $data['store_id'],
            ],
            $updateData
        );
    }

    protected function handleVariants(VendorDesignTemplateStore $storeOverride, array $variants): void
    {
        // 1. Identify IDs to keep from input
        $keepCatalogIds = collect($variants)
            ->pluck('catalog_product_id')
            ->filter()
            ->unique()
            ->toArray();

        // 2. Delete variants not in input (Sync/Cleanup)
        // If variants array is empty, this will delete all store variants for this template
        $storeOverride->variants()
            ->whereNotIn('catalog_product_id', $keepCatalogIds)
            ->delete();

        // 3. Upsert input variants
        foreach ($variants as $variantData) {
            if (empty($variantData['catalog_product_id'])) {
                continue;
            }

            $storeOverride->variants()->updateOrCreate(
                ['catalog_product_id' => $variantData['catalog_product_id']],
                [
                    // SKU is auto-generated by sync services, do not accept input
                    // 'sku' => $variantData['sku'] ?? null,
                    'markup' => $variantData['markup'] ?? null,
                    'markup_type' => $variantData['markup_type'] ?? 'percentage',
                    'external_variant_id' => $variantData['external_variant_id'] ?? null,
                    'is_enabled' => $variantData['is_enabled'] ?? true,
                ]
            );
        }
    }

    protected function handlePrimaryImage(VendorDesignTemplateStore $storeOverride, mixed $imageInput): void
    {
        if (is_array($imageInput)) {
            $imageInput = $imageInput['image'] ?? null;
        }

        if (is_string($imageInput)) {
            $imageInput = trim($imageInput);
        }

        $relativePath = $this->parseStoragePath($imageInput);

        if (! $relativePath) {
            return;
        }

        $primaryImage = $storeOverride->primaryImage;

        if ($primaryImage) {
            // Update existing primary image record if path changed
            if ($primaryImage->image_path !== $relativePath) {
                $primaryImage->update(['image_path' => $relativePath]);
            }
        } else {
            $storeOverride->images()->create([
                'image_path' => $relativePath,
                'is_primary' => true,
            ]);
        }
    }

    protected function handleSyncImages(VendorDesignTemplateStore $storeOverride, array $syncImagesData): void
    {
        $existingSyncImages = $storeOverride->syncImages;

        // 1. Resolve inputs to valid paths
        $validPaths = [];
        foreach ($syncImagesData as $item) {
            $url = is_array($item) ? ($item['image'] ?? null) : $item;
            if (is_string($url) && ! empty($url)) {
                $path = $this->parseStoragePath(trim($url));
                if ($path) {
                    $validPaths[] = $path;
                }
            }
        }
        $validPaths = array_unique($validPaths);

        // 2. Delete images not in input
        foreach ($existingSyncImages as $img) {
            if (! in_array($img->image_path, $validPaths)) {
                $img->delete();
            }
        }

        // 3. Add new images
        $existingPaths = $existingSyncImages->pluck('image_path')->toArray();
        foreach ($validPaths as $path) {
            if (! in_array($path, $existingPaths)) {
                $storeOverride->images()->create([
                    'image_path' => $path,
                    'is_primary' => false,
                ]);
            }
        }
    }

    private function generateFilename(int $storeId, string $extension): string
    {
        return 'store_'.$storeId.'_'.Str::random(20).'.'.$extension;
    }

    private function parseStoragePath(string $url): ?string
    {
        $path = $url;
        if (filter_var($url, FILTER_VALIDATE_URL)) {
            $path = parse_url($url, PHP_URL_PATH);
            if (! $path) {
                return null;
            }
        }

        // Decode
        $path = urldecode($path);

        // Normalize slashes
        $path = str_replace('\\', '/', $path);

        // Remove leading slashes
        $path = ltrim($path, '/');

        // Prevent path traversal
        $parts = explode('/', $path);
        $stack = [];
        foreach ($parts as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }
            if ($part === '..') {
                if (empty($stack)) {
                    // Trying to escape root
                    return null;
                }
                array_pop($stack);
            } else {
                $stack[] = $part;
            }
        }

        $normalizedPath = implode('/', $stack);

        if (Storage::exists($normalizedPath)) {
            return $normalizedPath;
        }

        return null;
    }
}
