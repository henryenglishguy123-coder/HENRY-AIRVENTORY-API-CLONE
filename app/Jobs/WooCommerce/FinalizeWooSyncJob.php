<?php

declare(strict_types=1);

namespace App\Jobs\WooCommerce;

use App\Models\Customer\Designer\VendorDesignTemplateStore;
use App\Support\Metrics;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class FinalizeWooSyncJob implements ShouldQueue
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

        Log::info('Sync job started', [
            'job' => class_basename($this),
            'store_id' => $storeId,
            'batch_id' => $this->batchId ?? null,
            'timestamp' => now(),
        ]);

        try {
            // If part of a batch, check for failures
            if ($this->batch()) {
                if ($this->batch()->cancelled()) {
                    return;
                }
                if ($this->batch()->hasFailures()) {
                    $this->hasFailures = true;
                }
            }

            if ($this->hasFailures) {
                $this->storeOverride->update([
                    'sync_status' => 'synced', // Mark as synced but with error? Or 'failed'?
                    // Usually if partial, we might want 'synced' so it doesn't block, but show error.
                    // Let's stick to 'synced' but add error message.
                    'sync_error' => 'Sync completed with some variation failures. Check logs.',
                ]);
                Log::warning('WooCommerce Sync finalized with failures', ['store_override_id' => $this->storeOverride->id]);

                Metrics::increment('woo.sync.finalize.completed', 1, [
                    'status' => 'partial_failure',
                    'store_id' => $storeId,
                ]);
            } else {
                $this->storeOverride->update([
                    'sync_status' => 'synced',
                    'sync_error' => null,
                ]);
                Log::info('WooCommerce Sync finalized successfully', ['store_override_id' => $this->storeOverride->id]);

                Metrics::increment('woo.sync.finalize.completed', 1, [
                    'status' => 'success',
                    'store_id' => $storeId,
                ]);
            }

            // Update connected store last_synced_at
            if ($this->storeOverride->connectedStore) {
                $this->storeOverride->connectedStore->update(['last_synced_at' => now()]);
            }

            $duration = (microtime(true) - $startTime) * 1000;

            Log::info('Sync job completed', [
                'job' => class_basename($this),
                'duration_ms' => $duration,
                'has_failures' => $this->hasFailures,
            ]);

            Metrics::histogram('woo.sync.finalize.duration', $duration, [
                'store_id' => $storeId,
            ]);

        } catch (\Throwable $e) {
            Log::error('FinalizeWooSyncJob failed', [
                'error' => $e->getMessage(),
                'store_override_id' => $this->storeOverride->id,
            ]);
            throw $e;
        }
    }
}
