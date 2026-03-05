<?php

declare(strict_types=1);

namespace App\Jobs\Shopify;

use App\Models\Customer\Cart\CartSource;
use App\Models\Customer\Store\VendorConnectedStore;
use App\Services\Channels\Shopify\ShopifyConnector;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncMissingShopifyOrdersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var int[]
     */
    public $backoff = [60, 120, 300];

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 3600; // Configurable for high-volume stores

    public int $days;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $shopDomain,
        int $days = 30
    ) {
        // Ensure days is always positive
        $this->days = max(1, $days);
        $this->onQueue(config('order_sync.queue', 'low'));
    }

    /**
     * Execute the job.
     */
    public function handle(ShopifyConnector $connector): void
    {
        if (!config('order_sync.enabled', true)) {
            Log::info('Shopify missing orders sync skipped (disabled)', [
                'shop' => $this->shopDomain,
            ]);
            return;
        }

        Log::info('Starting Shopify missing orders sync', [
            'shop' => $this->shopDomain,
            'days' => $this->days,
        ]);

        $store = null;
        try {
            // Find the store
            $store = VendorConnectedStore::where('store_identifier', $this->shopDomain)->first();
            
            if (!$store) {
                Log::error('Shopify store not found for sync', [
                    'shop' => $this->shopDomain,
                ]);
                return;
            }

            // Calculate date range
            $sinceDate = now()
                ->subDays($this->days)
                ->subMinutes((int) config('order_sync.lookback_minutes', 10))
                ->toIso8601String();
            
            $totalFetched = 0;
            $totalMissing = 0;
            $totalSkipped = 0;
            $errors = [];

            // Fetch orders from Shopify
            try {
                $orders = $connector->fetchOrders($store, $sinceDate, 'paid');
            } catch (Throwable $e) {
                Log::error('Shopify fetchOrders failed', [
                    'shop' => $this->shopDomain,
                    'error' => $e->getMessage(),
                ]);
                // Rethrow to allow retry mechanism
                throw $e;
            }
            
            $totalFetched = count($orders);
            
            // Batch-fetch existing orders to avoid N+1 queries
            $orderIds = array_map(fn($order) => (string) $order['id'], $orders);
            $existingOrderIds = CartSource::query()
                ->where('platform', 'shopify')
                ->where('source', $this->shopDomain)
                ->whereIn('source_order_id', $orderIds)
                ->pluck('source_order_id')
                ->flip()
                ->all();
            
            foreach ($orders as $order) {
                
                $orderId = $order['id'] ?? null;
                $orderNumber = $order['order_number'] ?? null;
                
                if (!$orderId) {
                    $totalSkipped++;
                    continue;
                }

                // Check if order already exists using pre-fetched set
                if (isset($existingOrderIds[$orderId])) {
                    $totalSkipped++;
                    Log::debug('Shopify order already imported, skipping', [
                        'shop' => $this->shopDomain,
                        'order_id' => $orderId,
                        'order_number' => $orderNumber,
                    ]);
                    continue;
                }

                // Check if order has line items
                if (empty($order['line_items'])) {
                    $totalSkipped++;
                    Log::debug('Shopify order has no line items, skipping', [
                        'shop' => $this->shopDomain,
                        'order_id' => $orderId,
                        'order_number' => $orderNumber,
                    ]);
                    continue;
                }

                // Dispatch order import job
                try {
                    ProcessShopifyOrderJob::dispatch($this->shopDomain, $order);
                    $totalMissing++;
                    
                    Log::info('Dispatched missing Shopify order for import', [
                        'shop' => $this->shopDomain,
                        'order_id' => $orderId,
                        'order_number' => $orderNumber,
                    ]);
                } catch (Throwable $e) {
                    $errors[] = [
                        'order_id' => $orderId,
                        'error' => $e->getMessage(),
                    ];
                    Log::error('Failed to dispatch Shopify order import', [
                        'shop' => $this->shopDomain,
                        'order_id' => $orderId,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Log summary
            Log::info('Completed Shopify missing orders sync', [
                'shop' => $this->shopDomain,
                'days' => $this->days,
                'total_fetched' => $totalFetched,
                'total_missing' => $totalMissing,
                'total_skipped' => $totalSkipped,
                'errors_count' => count($errors),
                'errors' => $errors, // Include actual errors for troubleshooting
            ]);
            
            // Update last sync timestamp only on successful completion
            if ($store) {
                try {
                    $store->update(['last_order_sync_at' => now()]);
                } catch (\Throwable $e) {
                    Log::warning('Failed to update last_order_sync_at for Shopify store', [
                        'shop' => $this->shopDomain,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

        } catch (Throwable $e) {
            Log::error('Failed to sync missing Shopify orders', [
                'shop' => $this->shopDomain,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
