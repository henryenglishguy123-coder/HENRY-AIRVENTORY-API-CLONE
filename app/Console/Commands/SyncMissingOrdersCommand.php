<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\Shopify\SyncMissingShopifyOrdersJob;
use App\Jobs\WooCommerce\SyncMissingWooOrdersJob;
use App\Models\Customer\Store\VendorConnectedStore;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SyncMissingOrdersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'orders:sync-missing {--days= : Number of days to look back} {--store= : Specific store ID or identifier to sync}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync missing paid orders from all connected Shopify and WooCommerce stores';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (!config('order_sync.enabled', true)) {
            $this->warn('Order sync is disabled by configuration');
            return self::SUCCESS;
        }

        $daysOpt = $this->option('days');
        // Treat empty strings as unset - only cast when non-empty
        $days = ($daysOpt !== null && trim((string)$daysOpt) !== '')
            ? (int) $daysOpt
            : (int) config('order_sync.default_days', 7);

        if ($days < 1) {
            $this->error('Days must be at least 1');
            return self::FAILURE;
        }
        $specificStoreInput = $this->option('store');
        $maxDays = (int) config('order_sync.max_days', 90);
        $queue = (string) config('order_sync.queue', 'low');
        $lockSeconds = (int) config('order_sync.lock_seconds', 1800);

        // Validate days
        if ($days < 1 || $days > $maxDays) {
            $this->error("Days must be between 1 and {$maxDays}");
            return self::FAILURE;
        }

        // Prevent overlapping runs
        $lock = Cache::lock('orders:sync-missing:lock', $lockSeconds);
        if (! $lock->get()) {
            $this->warn('Another sync run is in progress. Skipping this execution.');
            return self::SUCCESS;
        }

        $releaseLock = function () use ($lock) {
            try { $lock->release(); } catch (\Throwable $e) { /* ignore */ }
        };

        $this->info("Starting order sync for last {$days} days...");

        try {
            // Build query for stores
            $query = VendorConnectedStore::query()
                ->whereNotNull('token')
                ->where('status', 'connected');

            if ($specificStoreInput) {
                if (is_numeric($specificStoreInput)) {
                    $query->where('id', (int) $specificStoreInput);
                } else {
                    $query->where('store_identifier', $specificStoreInput);
                }
            }

            $total = (clone $query)->count();

            if ($total === 0) {
                $this->warn('No active stores found');
                return self::SUCCESS;
            }

            $this->info("Found {$total} active store(s)");

            $shopifyCount = 0;
            $wooCount = 0;
            $skipped = 0;
            $failed = 0;

            $query->orderBy('id')->chunkById(100, function ($stores) use (&$shopifyCount, &$wooCount, &$skipped, &$failed, $specificStoreInput, $queue, $days) {
                foreach ($stores as $store) {
                    $platform = $store->channel ?? 'unknown';

                    // Check if synced recently unless specific store requested
                    if (!$specificStoreInput && $this->wasSyncedRecently($store)) {
                        $this->line("⏭️  Skipping {$store->store_name} ({$platform}) - synced recently");
                        $skipped++;
                        continue;
                    }

                    try {
                        if ($platform === 'shopify') {
                            $shopDomain = $store->store_identifier;
                            if ($shopDomain) {
                            SyncMissingShopifyOrdersJob::dispatch($shopDomain, $days)
                                    ->onQueue($queue);
                                $this->info("✅ Dispatched Shopify sync: {$store->store_name} ({$shopDomain})");
                                $shopifyCount++;
                            } else {
                                $this->line("⏭️  Skipping {$store->store_name} - missing store_identifier");
                                $skipped++;
                            }
                        } elseif ($platform === 'woocommerce') {
                            SyncMissingWooOrdersJob::dispatch($store->id, $days)
                                ->onQueue($queue);
                            $this->info("✅ Dispatched WooCommerce sync: {$store->store_name} (ID: {$store->id})");
                            $wooCount++;
                        } else {
                            $this->line("⏭️  Skipping {$store->store_name} - unsupported platform: {$platform}");
                            $skipped++;
                        }
                    } catch (\Throwable $e) {
                        $this->error("❌ Failed to dispatch sync for {$store->store_name}: {$e->getMessage()}");
                        $failed++;
                        Log::error('Order sync command failed for store', [
                            'store_id' => $store->id,
                            'platform' => $platform,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            });

            $this->newLine();
            $this->info("📊 Summary:");
            $this->table(
                ['Platform', 'Count'],
                [
                    ['Shopify', $shopifyCount],
                    ['WooCommerce', $wooCount],
                    ['Skipped', $skipped],
                    ['Failed', $failed],
                    ['Total Processed', $shopifyCount + $wooCount + $failed],
                ]
            );

            \Illuminate\Support\Facades\Log::info('Order sync command completed', [
                'days' => $days,
                'shopify_count' => $shopifyCount,
                'woocommerce_count' => $wooCount,
                'skipped' => $skipped,
                'failed' => $failed,
            ]);

            return self::SUCCESS;
        } finally {
            $releaseLock();
        }
    }

    /**
     * Check if store was synced recently (within last 6 hours)
     */
    protected function wasSyncedRecently(VendorConnectedStore $store): bool
    {
        if (!$store->last_order_sync_at) {
            return false;
        }

        $hours = (int) config('order_sync.min_hours_between_syncs', 6);
        return $store->last_order_sync_at->greaterThan(now()->subHours($hours));
    }
}
