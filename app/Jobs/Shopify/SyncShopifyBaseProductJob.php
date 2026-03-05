<?php

declare(strict_types=1);

namespace App\Jobs\Shopify;

use App\Jobs\SyncBatchCompletion;
use App\Models\Customer\Designer\VendorDesignTemplateStore;
use App\Services\Channels\Factory\StoreConnectorFactory;
use App\Services\Channels\Shopify\ShopifyConnector;
use App\Services\Channels\Shopify\ShopifyDataService;
use App\Support\Metrics;
use Illuminate\Bus\Batch;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

class SyncShopifyBaseProductJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;

    public $backoff = [60, 120, 300];

    public $timeout = 300;

    public function __construct(
        protected VendorDesignTemplateStore $storeOverride
    ) {
        $this->onQueue('high');
    }

    public function handle(StoreConnectorFactory $connectorFactory, ShopifyDataService $dataService): void
    {
        $startTime = microtime(true);
        $storeId = $this->storeOverride->vendor_connected_store_id;

        if ($this->batch() && $this->batch()->cancelled()) {
            return;
        }

        // Rate Limiting
        $key = 'shopify-sync:'.$storeId;

        // Use lock to ensure atomic rate limiting check-and-hit
        $lock = Cache::lock('lock:'.$key, 5);

        if ($lock->get()) {
            try {
                if (RateLimiter::tooManyAttempts($key, 60)) { // 60 requests per minute bucket
                    $this->release(60);

                    return;
                }
                RateLimiter::hit($key, 60);
            } finally {
                $lock->release();
            }
        } else {
            // Could not get lock, retry shortly
            $this->release(5);

            return;
        }

        Log::info('Shopify Sync job started', [
            'job' => class_basename($this),
            'store_id' => $storeId,
            'store_override_id' => $this->storeOverride->id,
            'batch_id' => $this->batchId ?? null,
            'timestamp' => now(),
        ]);

        try {
            // Update status to syncing
            $this->storeOverride->update([
                'sync_status' => 'syncing',
                'sync_error' => null,
            ]);

            // Load necessary relationships
            $this->storeOverride->load(['connectedStore.storeChannel']);
            $connectedStore = $this->storeOverride->connectedStore;

            if (! $connectedStore || ! $connectedStore->storeChannel) {
                throw new \Exception('Store channel configuration missing');
            }

            /** @var ShopifyConnector $connector */
            $connector = $connectorFactory->make($connectedStore->storeChannel);

            // Sync Base Product
            // This method should return the Shopify Product ID
            $shopifyId = $connector->syncBaseProduct($this->storeOverride);

            // Prepare batches for variations
            $batches = $dataService->getVariationBatches($this->storeOverride);
            Log::info('ShopifyDataService: Prepared variation batches generator');
            $jobs = [];
            $variationCount = 0;

            foreach ($batches as $batchData) {
                if (empty($batchData['create']) && empty($batchData['update'])) {
                    continue;
                }

                $jobs[] = new SyncShopifyVariationBatchJob(
                    $this->storeOverride,
                    $shopifyId,
                    $batchData['create'],
                    $batchData['update']
                );

                $variationCount += count($batchData['create']) + count($batchData['update']);
            }

            if (empty($jobs)) {
                // No variations to sync, finalize immediately
                FinalizeShopifySyncJob::dispatch($this->storeOverride);
            } else {
                // Dispatch batch
                $storeOverrideId = $this->storeOverride->id;

                Bus::batch($jobs)
                    ->name('Shopify Sync: '.$this->storeOverride->name)
                    ->allowFailures()
                    ->onQueue('default')
                    ->finally(new SyncBatchCompletion($storeOverrideId, FinalizeShopifySyncJob::class))
                    ->dispatch();
            }

            $duration = (microtime(true) - $startTime) * 1000;

            Log::info('Shopify Sync job completed', [
                'job' => class_basename($this),
                'duration_ms' => $duration,
                'items_processed' => 1, // Base product
                'variations_queued' => $variationCount,
                'jobs_dispatched' => count($jobs),
            ]);

            Metrics::increment('shopify.sync.jobs.completed', 1, [
                'type' => 'base_product',
                'store_id' => $storeId,
            ]);

            Metrics::histogram('shopify.sync.base_product.duration', $duration, [
                'store_id' => $storeId,
            ]);

        } catch (\Throwable $e) {
            $duration = (microtime(true) - $startTime) * 1000;

            Log::error('Shopify Sync job failed', [
                'job' => class_basename($this),
                'store_id' => $storeId,
                'error' => $e->getMessage(),
                'duration_ms' => $duration,
            ]);

            Metrics::increment('shopify.sync.jobs.failed', 1, [
                'type' => 'base_product',
                'store_id' => $storeId,
                'reason' => class_basename($e),
            ]);

            $this->storeOverride->update([
                'sync_status' => 'failed',
                'sync_error' => \Illuminate\Support\Str::limit($e->getMessage(), 252, '...'),
            ]);

            throw $e;
        }
    }
}
