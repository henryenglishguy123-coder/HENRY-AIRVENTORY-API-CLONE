<?php

declare(strict_types=1);

namespace App\Jobs\Shopify;

use App\Models\Customer\Designer\VendorDesignTemplateStore;
use App\Services\Channels\Factory\StoreConnectorFactory;
use App\Services\Channels\Shopify\ShopifyConnector;
use App\Support\Metrics;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncShopifyVariationBatchJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;

    public $backoff = [60, 120, 300];

    public $timeout = 300;

    public function __construct(
        protected VendorDesignTemplateStore $storeOverride,
        protected string $shopifyProductId,
        protected array $batchCreate,
        protected array $batchUpdate
    ) {
        $this->onQueue('default');
    }

    public function handle(StoreConnectorFactory $connectorFactory): void
    {
        $startTime = microtime(true);
        $storeId = $this->storeOverride->vendor_connected_store_id;

        if ($this->batch() && $this->batch()->cancelled()) {
            return;
        }

        try {
            if (! $this->storeOverride->relationLoaded('connectedStore')) {
                $this->storeOverride->load(['connectedStore.storeChannel']);
            }

            $connectedStore = $this->storeOverride->connectedStore;

            if (! $connectedStore) {
                Log::error('Shopify variation batch sync job failed: Connected store not found', [
                    'job' => class_basename($this),
                    'store_override_id' => $this->storeOverride->id,
                    'store_id' => $storeId,
                ]);

                Metrics::increment('shopify.sync.jobs.failed', 1, [
                    'type' => 'variation_batch',
                    'store_id' => $storeId,
                    'reason' => 'ConnectedStoreNotFound',
                ]);

                // We can return early or throw, but since this is a job that expects a store, failing is appropriate.
                // However, without a connected store, we can't really "fail" in a way that helps retry if the data is missing.
                // But following instructions: "log or throw a clear error ... and mark the job as failed/return early"
                return;
            }

            /** @var ShopifyConnector $connector */
            $connector = $connectorFactory->make($connectedStore->storeChannel);

            $connector->syncVariationBatch(
                $this->shopifyProductId,
                $this->storeOverride,
                $this->batchCreate,
                $this->batchUpdate
            );

            $duration = (microtime(true) - $startTime) * 1000;
            $itemsProcessed = count($this->batchCreate) + count($this->batchUpdate);

            Log::info('Shopify variation batch sync job completed', [
                'job' => class_basename($this),
                'duration_ms' => $duration,
                'items_processed' => $itemsProcessed,
                'success_rate' => 1.0,
            ]);

            Metrics::increment('shopify.sync.jobs.completed', 1, [
                'type' => 'variation_batch',
                'store_id' => $storeId,
            ]);

            Metrics::histogram('shopify.sync.batch.duration', $duration, [
                'batch_size' => $itemsProcessed,
                'store_id' => $storeId,
            ]);

        } catch (\Throwable $e) {
            Metrics::increment('shopify.sync.jobs.failed', 1, [
                'type' => 'variation_batch',
                'store_id' => $storeId,
                'reason' => class_basename($e),
            ]);

            Log::error('Shopify variation batch sync job failed', [
                'job' => class_basename($this),
                'store_id' => $storeId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        $storeId = $this->storeOverride->vendor_connected_store_id ?? 'unknown';
        $batchSize = count($this->batchCreate) + count($this->batchUpdate);

        Metrics::increment('shopify.sync.jobs.permanently_failed', 1, [
            'type' => 'variation_batch',
            'store_id' => $storeId,
            'reason' => class_basename($exception),
            'batch_size' => $batchSize,
        ]);

        Log::error('Shopify variation batch sync job permanently failed', [
            'job' => class_basename($this),
            'store_id' => $storeId,
            'shopify_product_id' => $this->shopifyProductId,
            'batch_size' => $batchSize,
            'error' => $exception->getMessage(),
        ]);
    }
}
