<?php

declare(strict_types=1);

namespace App\Jobs\WooCommerce;

use App\Jobs\SyncBatchCompletion;
use App\Models\Customer\Designer\VendorDesignTemplateStore;
use App\Services\Channels\Factory\StoreConnectorFactory;
use App\Services\Channels\WooCommerce\WooCommerceConnector;
use App\Services\Channels\WooCommerce\WooCommerceDataService;
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

class SyncWooBaseProductJob implements ShouldQueue
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

    public function handle(StoreConnectorFactory $connectorFactory, WooCommerceDataService $dataService): void
    {
        $startTime = microtime(true);
        $storeId = $this->storeOverride->vendor_connected_store_id;

        // Rate Limiting
        $key = 'woo-sync:'.$storeId;

        // Use lock to ensure atomic rate limiting check-and-hit
        $lock = Cache::lock('lock:'.$key, 5);

        if ($lock->get()) {
            try {
                if (RateLimiter::tooManyAttempts($key, 60)) { // 60 requests per minute per store
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

        if ($this->batch() && $this->batch()->cancelled()) {
            return;
        }

        Log::info('Sync job started', [
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

            /** @var WooCommerceConnector $connector */
            $connector = $connectorFactory->make($connectedStore->storeChannel);

            // Sync Base Product
            $wooId = $connector->syncBaseProduct($this->storeOverride);

            Log::info('Base product synced, checking variants', [
                'store_override_id' => $this->storeOverride->id,
                'variants_count' => $this->storeOverride->variants->count(),
            ]);

            // Prepare Variations
            $jobs = [];
            $variationCount = 0;
            foreach ($dataService->getVariationBatches($this->storeOverride) as $i => $batch) {
                $batchSize = count($batch['create']) + count($batch['update']);
                $variationCount += $batchSize;

                Log::info('Processing variation batch', [
                    'batch_index' => $i,
                    'create_count' => count($batch['create']),
                    'update_count' => count($batch['update']),
                ]);
                if (! empty($batch['create']) || ! empty($batch['update'])) {
                    $jobs[] = new SyncWooVariationBatchJob(
                        $this->storeOverride,
                        $wooId,
                        $batch['create'],
                        $batch['update']
                    );
                }
            }

            if (empty($jobs)) {
                Log::info('No variation jobs to dispatch', ['store_override_id' => $this->storeOverride->id]);
                // No variations, just finalize
                FinalizeWooSyncJob::dispatch($this->storeOverride);
            } else {
                // Dispatch batch
                $storeOverrideId = $this->storeOverride->id;

                Bus::batch($jobs)
                    ->name('WooCommerce Sync: '.$this->storeOverride->name)
                    ->allowFailures()
                    ->onQueue('default')
                    ->finally(new SyncBatchCompletion($storeOverrideId, FinalizeWooSyncJob::class))
                    ->dispatch();
            }

            $duration = (microtime(true) - $startTime) * 1000;

            Log::info('Sync job completed', [
                'job' => class_basename($this),
                'duration_ms' => $duration,
                'items_processed' => 1, // Base product
                'variations_queued' => $variationCount,
                'jobs_dispatched' => count($jobs),
            ]);

            Metrics::increment('woo.sync.jobs.dispatched', 1, [
                'type' => 'base_product',
                'store_id' => $storeId,
            ]);

            Metrics::histogram('woo.sync.base_product.duration', $duration, [
                'store_id' => $storeId,
            ]);

        } catch (\Throwable $e) {
            $duration = (microtime(true) - $startTime) * 1000;
            Log::error('SyncWooBaseProductJob failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'store_override_id' => $this->storeOverride->id,
                'duration_ms' => $duration,
            ]);

            Metrics::increment('woo.sync.jobs.failed', 1, [
                'type' => 'base_product',
                'store_id' => $storeId,
                'error' => get_class($e),
            ]);

            $this->storeOverride->update([
                'sync_status' => 'failed',
                'sync_error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
