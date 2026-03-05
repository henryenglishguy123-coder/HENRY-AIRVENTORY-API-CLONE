<?php

declare(strict_types=1);

namespace App\Jobs\Shopify;

use App\Models\Customer\Designer\VendorDesignTemplateStore;
use App\Support\Metrics;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class FinalizeShopifySyncJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;

    public $backoff = [60, 120, 300];

    public $timeout = 60;

    public function __construct(
        protected VendorDesignTemplateStore $storeOverride,
        protected bool $hasFailures = false
    ) {
        $this->onQueue('low');
    }

    public function handle(): void
    {
        $startTime = microtime(true);
        $storeId = $this->storeOverride->vendor_connected_store_id;

        Log::info('Finalizing Shopify Sync', [
            'store_override_id' => $this->storeOverride->id,
            'store_id' => $storeId,
            'has_failures' => $this->hasFailures,
            'batch_id' => $this->batchId ?? null,
        ]);

        try {
            if ($this->hasFailures || ($this->batch() && $this->batch()->hasFailures())) {
                $this->storeOverride->update([
                    'sync_status' => 'failed',
                    'sync_error' => 'One or more variation batches failed to sync.',
                ]);
                Log::error('Shopify Sync finalized with errors', [
                    'store_override_id' => $this->storeOverride->id,
                    'store_id' => $storeId,
                ]);

                Metrics::increment('shopify.sync.finalize.completed', 1, [
                    'status' => 'failed',
                ]);
            } else {
                $this->storeOverride->update([
                    'sync_status' => 'synced',
                    'sync_error' => null,
                ]);
                Log::info('Shopify Sync finalized successfully', [
                    'store_override_id' => $this->storeOverride->id,
                    'store_id' => $storeId,
                ]);

                // Update connected store last_synced_at
                if ($this->storeOverride->connectedStore) {
                    $this->storeOverride->connectedStore->update(['last_synced_at' => now()]);
                } else {
                    Log::warning('FinalizeShopifySyncJob: Connected store not found for last_synced_at update', [
                        'store_override_id' => $this->storeOverride->id,
                        'store_id' => $storeId,
                        'job_id' => $this->job ? $this->job->getJobId() : null,
                    ]);
                }

                Metrics::increment('shopify.sync.finalize.completed', 1, [
                    'status' => 'success',
                ]);
            }

            $duration = (microtime(true) - $startTime) * 1000;

            Log::info('Sync job completed', [
                'job' => class_basename($this),
                'store_id' => $storeId,
                'duration_ms' => $duration,
                'has_failures' => $this->hasFailures,
            ]);

            Metrics::histogram('shopify.sync.finalize.duration', $duration);

        } catch (\Throwable $e) {
            Log::error('FinalizeShopifySyncJob failed', [
                'error' => $e->getMessage(),
                'store_override_id' => $this->storeOverride->id,
                'store_id' => $storeId,
            ]);
            throw $e;
        }
    }
}
