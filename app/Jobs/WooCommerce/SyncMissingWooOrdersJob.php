<?php

declare(strict_types=1);

namespace App\Jobs\WooCommerce;

use App\Models\Customer\Cart\CartSource;
use App\Models\Customer\Store\VendorConnectedStore;
use App\Services\Channels\WooCommerce\WooCommerceConnector;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncMissingWooOrdersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [60, 120, 300];

    public int $timeout = 300;

    public int $days;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $storeId,
        int $days = 30
    ) {
        // Ensure days is always positive
        $this->days = max(1, $days);
        $this->onQueue(config('order_sync.queue', 'low'));
    }

    /**
     * Execute the job.
     */
    public function handle(WooCommerceConnector $connector): void
    {
        if (! config('order_sync.enabled', true)) {
            Log::info('WooCommerce missing orders sync skipped (disabled)', [
                'store_id' => $this->storeId,
            ]);

            return;
        }

        Log::info('Starting WooCommerce missing orders sync', [
            'store_id' => $this->storeId,
            'days' => $this->days,
        ]);

        $store = null;
        try {
            // Find the store - use findOrFail to allow retries on DB issues
            try {
                $store = VendorConnectedStore::findOrFail($this->storeId);
            } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
                // Store deleted/missing - log and exit without retry
                Log::error('WooCommerce store not found for sync (deleted or invalid ID)', [
                    'store_id' => $this->storeId,
                ]);

                return;
            }

            // Get store domain for source tracking - match logic from OrderImportService
            $source = $this->extractSourceFromLink($store->link);

            // Calculate date range
            $sinceDate = now()
                ->subDays($this->days)
                ->subMinutes((int) config('order_sync.lookback_minutes', 10))
                ->toIso8601String();

            $totalFetched = 0;
            $totalMissing = 0;
            $totalSkipped = 0;
            $errors = [];

            // Fetch orders from WooCommerce (both completed and processing are considered paid)
            $orders = $connector->fetchOrders($store, $sinceDate, ['completed', 'processing']);

            $totalFetched = count($orders);
            // Extract source for consistency
            $source = $this->extractSourceFromLink($store->store_link);

            // Process orders in chunks
            $chunkSize = (int) config('order_sync.chunk_size', 50);
            $chunkDelayMs = (int) config('order_sync.chunk_delay_ms', 100);
            $chunks = array_chunk($orders, $chunkSize);

            foreach ($chunks as $chunkIndex => $chunk) {
                // Batch-fetch existing orders for this chunk to avoid N+1 queries
                $chunkOrderIds = array_map(fn ($order) => (string) $order['id'], $chunk);
                $existingOrderIds = CartSource::query()
                    ->where('platform', 'woocommerce')
                    ->where('source', $source)
                    ->whereIn('source_order_id', $chunkOrderIds)
                    ->pluck('source_order_id')
                    ->flip()
                    ->all();

                foreach ($chunk as $order) {
                    $orderId = (string) $order['id'];
                    $orderNumber = $order['number'] ?? $orderId;

                    if (! $orderId) {
                        $totalSkipped++;

                        continue;
                    }

                    // Check if order already exists using pre-fetched set
                    if (isset($existingOrderIds[$orderId])) {
                        $totalSkipped++;
                        Log::debug('WooCommerce order already imported, skipping', [
                            'store_id' => $this->storeId,
                            'order_id' => $orderId,
                            'order_number' => $orderNumber,
                        ]);

                        continue;
                    }

                    // Check if order has line items
                    if (empty($order['line_items']) || ! is_array($order['line_items'])) {
                        $totalSkipped++;
                        Log::debug('WooCommerce order has no line items, skipping', [
                            'store_id' => $this->storeId,
                            'order_id' => $orderId,
                            'order_number' => $orderNumber,
                        ]);

                        continue;
                    }

                    // Dispatch order import job
                    try {
                        // Determine topic based on order status for consistency
                        $topic = 'order.created';

                        ProcessWooCommerceOrderJob::dispatch($this->storeId, $topic, $order);
                        $totalMissing++;

                        Log::info('Dispatched missing WooCommerce order for import', [
                            'store_id' => $this->storeId,
                            'order_id' => $orderId,
                            'order_number' => $orderNumber,
                        ]);
                    } catch (Throwable $e) {
                        $errors[] = [
                            'order_id' => $orderId,
                            'error' => $e->getMessage(),
                        ];
                        Log::error('Failed to dispatch WooCommerce order import', [
                            'store_id' => $this->storeId,
                            'order_id' => $orderId,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }

                // Add delay between chunks (except for last chunk)
                if ($chunkDelayMs > 0 && $chunkIndex < count($chunks) - 1) {
                    usleep($chunkDelayMs * 1000);
                }
            }

            // Log summary
            Log::info('Completed WooCommerce missing orders sync', [
                'store_id' => $this->storeId,
                'days' => $this->days,
                'total_fetched' => $totalFetched,
                'total_missing' => $totalMissing,
                'total_skipped' => $totalSkipped,
                'errors_count' => count($errors),
            ]);

            // Update last sync timestamp only on successful completion
            if ($store) {
                try {
                    $store->update(['last_order_sync_at' => now()]);
                } catch (\Throwable $e) {
                    Log::warning('Failed to update last_order_sync_at for WooCommerce store', [
                        'store_id' => $this->storeId,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

        } catch (Throwable $e) {
            Log::error('Failed to sync missing WooCommerce orders', [
                'store_id' => $this->storeId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Rethrow to trigger retry mechanism
            throw $e;
        }
    }

    /**
     * Extract and normalize source domain from store link.
     * Matches logic from OrderImportService for consistency.
     */
    protected function extractSourceFromLink(?string $link): string
    {
        if (empty($link)) {
            return 'unknown';
        }

        $link = trim($link);
        $host = parse_url($link, PHP_URL_HOST);

        // If parsing failed or no host, try to extract manually
        if (! $host) {
            // Remove protocol if present
            $cleaned = preg_replace('#^https?://#i', '', $link);
            // Remove path and query
            $cleaned = explode('/', $cleaned)[0];
            $cleaned = explode('?', $cleaned)[0];

            // Remove port to match parse_url behavior (host only, no port)
            // Handle IPv6 addresses: [::1]:8080 -> [::1]
            if (str_starts_with($cleaned, '[')) {
                // IPv6 address
                $cleaned = explode(']', $cleaned)[0].']';
                $cleaned = trim($cleaned, '[]');
            } else {
                // Regular hostname/IPv4 - strip :port
                $cleaned = explode(':', $cleaned)[0];
            }

            return $cleaned ?: 'unknown';
        }

        return $host;
    }
}
