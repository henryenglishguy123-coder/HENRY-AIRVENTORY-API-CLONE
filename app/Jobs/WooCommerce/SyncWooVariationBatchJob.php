<?php

declare(strict_types=1);

namespace App\Jobs\WooCommerce;

use App\Models\Customer\Designer\VendorDesignTemplateStore;
use App\Services\Channels\Factory\StoreConnectorFactory;
use App\Services\Channels\WooCommerce\WooCommerceConnector;
use App\Support\Metrics;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

class SyncWooVariationBatchJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;

    public $backoff = [60, 120, 300];

    public $timeout = 300;

    public function __construct(
        protected VendorDesignTemplateStore $storeOverride,
        protected string $wooProductId,
        protected array $batchCreate,
        protected array $batchUpdate
    ) {
        $this->onQueue('default');
    }

    public function handle(StoreConnectorFactory $connectorFactory): void
    {
        $startTime = microtime(true);
        $storeId = $this->storeOverride->vendor_connected_store_id;

        // Rate Limiting
        $key = 'woo-sync:'.$storeId;
        if (RateLimiter::tooManyAttempts($key, 60)) {
            $this->release(60);

            return;
        }
        RateLimiter::hit($key, 60);

        if ($this->batch() && $this->batch()->cancelled()) {
            return;
        }

        // Idempotency / Concurrency Lock
        // Use a hash of the batch data to ensure unique lock per data set
        $batchHash = md5(json_encode(['c' => $this->batchCreate, 'u' => $this->batchUpdate]));
        $lockKey = "woo_sync_batch_lock:{$storeId}:{$this->wooProductId}:{$batchHash}";

        // Try to acquire lock for 10 seconds. If we can't, release back to queue.
        // This prevents parallel execution of the exact same batch payload.
        if (! Cache::lock($lockKey, 10)->get()) {
            $this->release(10);

            return;
        }

        try {
            Log::info('Sync job started', [
                'job' => class_basename($this),
                'store_id' => $storeId,
                'batch_id' => $this->batchId ?? null,
                'items_count' => count($this->batchCreate) + count($this->batchUpdate),
                'timestamp' => now(),
            ]);

            // Load necessary relationship to get store connection
            if (! $this->storeOverride->relationLoaded('connectedStore')) {
                $this->storeOverride->load(['connectedStore.storeChannel']);
            }

            $connectedStore = $this->storeOverride->connectedStore;

            /** @var WooCommerceConnector $connector */
            $connector = $connectorFactory->make($connectedStore->storeChannel);

            $connector->syncVariationBatch(
                $this->wooProductId,
                $this->storeOverride,
                $this->batchCreate,
                $this->batchUpdate
            );

            $duration = (microtime(true) - $startTime) * 1000;
            $itemsProcessed = count($this->batchCreate) + count($this->batchUpdate);

            Log::info('Sync job completed', [
                'job' => class_basename($this),
                'duration_ms' => $duration,
                'items_processed' => $itemsProcessed,
                'success_rate' => 1.0,
            ]);

            Metrics::increment('woo.sync.jobs.dispatched', 1, [
                'type' => 'variation_batch',
                'store_id' => $storeId,
            ]);

            Metrics::histogram('woo.sync.batch.duration', $duration, [
                'batch_size' => $itemsProcessed,
                'store_id' => $storeId,
            ]);

        } catch (\Throwable $e) {
            Metrics::increment('woo.sync.jobs.failed', 1, [
                'type' => 'variation_batch',
                'store_id' => $storeId,
                'reason' => class_basename($e),
            ]);

            Log::error('Sync job failed', [
                'job' => class_basename($this),
                'store_id' => $storeId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        } finally {
            // Optional: Release lock explicitly, or let it expire.
            // Letting it expire (10s) is safer if we want to prevent immediate replay.
            // But for standard locking, we usually release.
            // Given the nature of API calls, let's release it.
            Cache::lock($lockKey)->forceRelease();
        }
    }
}
