# Order Sync Cron

This cron synchronizes missing paid orders from all connected Shopify and WooCommerce stores. It is safe-by-default, resilient to individual store failures, and fully configurable via environment variables.

## What It Does

- Scans connected stores and dispatches per‑store jobs to fetch recent paid orders.
- Skips stores synced recently based on a configurable window.
- Ignores orders already imported (checked via `cart_sources`) using **batch queries** to avoid N+1 issues.
- Skips orders without line items.
- Uses a small lookback window to avoid missing orders around time boundaries.
- **Optimized**: Batch database lookups reduce queries by ~98% for high-volume stores.

## Where It Is Scheduled

- Scheduling is registered in [routes/console.php](../routes/console.php) and runs at the cron expression from `config('order_sync.schedule')` (defaults to 2 AM daily).
- Scheduler entry:
    - Respects `order_sync.enabled`.
    - Uses `withoutOverlapping()` to avoid concurrent scheduler runs.
    - The command itself also uses an internal lock for double safety.
    - Includes a description visible in `php artisan schedule:list`.

## Command

- Name: `orders:sync-missing`
- Location: [app/Console/Commands/SyncMissingOrdersCommand.php](../app/Console/Commands/SyncMissingOrdersCommand.php)
- Behavior:
    - Prevents overlapping runs with a cache lock.
    - Chunks stores to avoid memory spikes and isolates failures.
    - Honors recent-sync skip window unless a specific store is targeted.
    - Dispatches jobs on the configured queue.
    - Tracks and displays failed dispatch attempts in summary.
    - Shows "Total Processed" (excludes skipped) in final summary.

### Jobs

- Shopify: [app/Jobs/Shopify/SyncMissingShopifyOrdersJob.php](../app/Jobs/Shopify/SyncMissingShopifyOrdersJob.php)
- WooCommerce: [app/Jobs/WooCommerce/SyncMissingWooOrdersJob.php](../app/Jobs/WooCommerce/SyncMissingWooOrdersJob.php)

Both jobs:

- Exit early if `ORDER_SYNC_ENABLED=false`.
- Use a configurable lookback window (minutes) to avoid gaps.
- **Batch-fetch existing orders** using `whereIn()` to avoid N+1 database queries (~98% reduction).
- Catch connector errors and log them with full error details.
- **Rethrow exceptions** to trigger Laravel's retry mechanism (3 attempts with backoff).
- Update `last_order_sync_at` **only on successful completion** (not on failures).
- Process WooCommerce orders in configurable chunks with delays to prevent queue overwhelm.

**Shopify-specific optimizations:**

- Timeout increased to **3600 seconds** (1 hour) for high-volume stores.
- **Exponential backoff with jitter** for HTTP 429 rate limiting.
- Includes full error payload in completion logs for troubleshooting.

## Configuration

File: [config/order_sync.php](../config/order_sync.php)

| Key                         | Default      | Description                                                           |
| --------------------------- | ------------ | --------------------------------------------------------------------- |
| ORDER_SYNC_ENABLED          | true         | Global on/off switch for the cron and jobs                            |
| ORDER_SYNC_DEFAULT_DAYS     | 7            | Lookback window in days when not provided via CLI                     |
| ORDER_SYNC_MAX_DAYS         | 90           | Max allowed lookback days                                             |
| ORDER_SYNC_MIN_HOURS        | 6            | Minimum hours between syncs for the same store                        |
| ORDER_SYNC_SCHEDULE         | 0 2 \* \* \* | Cron expression used by scheduler                                     |
| ORDER_SYNC_QUEUE            | low          | Queue name for dispatching jobs                                       |
| ORDER_SYNC_LOOKBACK_MINUTES | 10           | Extra minutes subtracted from the lookback start to avoid edge misses |
| ORDER_SYNC_LOCK_SECONDS     | 1800         | Internal lock TTL to avoid overlapping command runs                   |
| ORDER_SYNC_CHUNK_SIZE       | 50           | Batch size for processing orders (prevents queue overwhelm)           |
| ORDER_SYNC_CHUNK_DELAY_MS   | 100          | Delay in milliseconds between processing chunks                       |

**Configuration Validation:**

- All values are normalized and validated in [config/order_sync.php](../config/order_sync.php).
- The [OrderSyncServiceProvider](../app/Providers/OrderSyncServiceProvider.php) validates `default_days <= max_days` at boot time.
- Invalid values are clamped and logged as warnings (doesn't crash bootstrap).

## Manual Usage

```bash
# Run with default lookback (ORDER_SYNC_DEFAULT_DAYS)
php artisan orders:sync-missing

# Run with custom lookback
php artisan orders:sync-missing --days=30

# Target a specific store by numeric ID
php artisan orders:sync-missing --store=123

# Target a specific store by identifier (e.g., Shopify domain)
php artisan orders:sync-missing --store=myshop.myshopify.com
```

## Data and Model Notes

- `vendor_connected_stores.last_order_sync_at` is used to track when a store last completed an order sync.
    - Migration: [database/migrations/2026_02_16_091725_add_last_order_sync_at_to_vendor_connected_stores.php](../database/migrations/2026_02_16_091725_add_last_order_sync_at_to_vendor_connected_stores.php)
    - **Indexed** for fast queries filtering by sync timestamp.
    - Model: [app/Models/Customer/Store/VendorConnectedStore.php](../app/Models/Customer/Store/VendorConnectedStore.php)

## Queue / Workers

Make sure your workers process the configured queue:

```bash
php artisan queue:work --queue=high,default,low
# or via Horizon
php artisan horizon
```

## Performance Optimizations

### N+1 Query Elimination

Both Shopify and WooCommerce jobs use **batch queries** to check for existing orders:

```php
// Before: N queries (one per order)
foreach ($orders as $order) {
    $exists = CartSource::where(...)->exists(); // N+1 problem
}

// After: 1 query per chunk
$orderIds = array_map(fn($order) => (string) $order['id'], $chunk);
$existingOrderIds = CartSource::whereIn('source_order_id', $orderIds)->pluck(...);
foreach ($chunk as $order) {
    if (isset($existingOrderIds[$orderId])) { /* skip */ }
}
```

**Impact**: ~98% reduction in database queries (e.g., 200 orders = 4 queries instead of 200).

### Rate Limiting (Shopify)

**Exponential backoff with jitter** for HTTP 429 responses:

- Respects `Retry-After` header
- Applies exponential backoff: `min(base * 2^(attempt-1), 60)`
- Adds 10% random jitter to distribute retry timing
- Prevents thundering herd problem

**Example retry delays**: 2s → 4s → 8s (plus jitter)

### Chunked Processing (WooCommerce)

Orders dispatched in configurable chunks (default: 50) with delays (default: 100ms) to prevent overwhelming the queue.

## Troubleshooting

- No active stores found:
    - Ensure stores are connected (`status=connected`) and have credentials.
- Job fetch errors:
    - See `storage/logs/laravel.log` for connector error logs with full context.
- Sync too frequent / too rare:
    - Tune `ORDER_SYNC_MIN_HOURS` and `ORDER_SYNC_SCHEDULE`.
- Queue not processing:
    - Confirm workers are running and include the `ORDER_SYNC_QUEUE`.
- Performance issues:
    - Check query count in logs - should see batch `whereIn` queries, not individual `exists()`.
    - Verify `last_order_sync_at` index exists: `SHOW INDEX FROM vendor_connected_stores;`
- Rate limiting (429 errors):
    - Check logs for "retrying with backoff" messages.
    - Exponential backoff should handle transient rate limits automatically.
- Job timeouts:
    - Shopify jobs timeout after 3600s (1 hour).
    - Jobs retry up to 3 times with backoff (60s, 120s, 300s).

## Connectors (References)

- Shopify fetch method: `fetchOrders` in [app/Services/Channels/Shopify/ShopifyConnector.php](../app/Services/Channels/Shopify/ShopifyConnector.php)
    - Includes exponential backoff with jitter for HTTP 429
    - Validates credentials before API calls
    - Warns if pagination safety limit is reached
- WooCommerce fetch method: `fetchOrders` in [app/Services/Channels/WooCommerce/WooCommerceConnector.php](../app/Services/Channels/WooCommerce/WooCommerceConnector.php)
    - Retries transient failures (3 attempts with increasing delays)
    - Validates `per_page` parameter (1-100 range)
    - Optimized array operations for large result sets

## System Cron Setup

Ensure Laravel’s scheduler runs every minute on your server (crontab):

```bash
* * * * * cd /path/to/airventory-api && php artisan schedule:run >> /dev/null 2>&1
```

You can verify scheduled tasks with:

```bash
php artisan schedule:list
```

## Recent Improvements

### Database

- ✅ Added index on `last_order_sync_at` for query performance

### Performance

- ✅ **N+1 query elimination**: Batch `whereIn()` lookups (~98% reduction)
- ✅ **Exponential backoff with jitter**: Shopify rate limit handling
- ✅ **Chunked processing**: Configurable batch size and delays

### Reliability

- ✅ **Exception rethrowing**: Jobs properly retry on failures
- ✅ **Success-based timestamps**: `last_order_sync_at` only updated on completion
- ✅ **Extended timeout**: Shopify jobs can run for 1 hour
- ✅ **Config validation**: Moved to service provider to prevent bootstrap crashes

### Observability

- ✅ **Enhanced logging**: Full error payloads in completion logs
- ✅ **Command descriptions**: Visible in `schedule:list`
- ✅ **Failed counter**: Track and display job dispatch failures
- ✅ **Detailed context**: All error logs include store_id, attempt count, etc.
