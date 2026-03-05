# WooCommerce Synchronization Architecture

## 1. Architecture Decision Record (ADR)

### Context
The previous synchronous synchronization method for WooCommerce products was prone to timeouts and memory exhaustion when dealing with products having large numbers of variations. Failures were difficult to recover from, often requiring a full re-sync.

### Decision
We adopted a **Job-Based, Batched, Asynchronous Architecture**.

### Architecture Overview
The synchronization process is broken down into a hierarchy of queued jobs:

1.  **Entry Point**: `VendorDesignTemplateStoreService` dispatches the product sync job directly.
2.  **`SyncWooBaseProductJob`**:
    - Syncs the parent (variable) product first.
    - Fetches variation data.
    - Chunks variations into batches (default: 50).
    - Dispatches a bus batch of `SyncWooVariationBatchJob`.
3.  **`SyncWooVariationBatchJob`**:
    - Processes a specific batch of variations.
    - Handles creation and updates of variations in WooCommerce.
    - Uses idempotency keys (Cache Lock) to prevent duplicate processing.
4.  **`FinalizeWooSyncJob`**:
    - Runs after all batches complete successfully.
    - Updates the main store status to 'connected'.
    - Cleans up temporary resources.

### Trade-offs & Benefits
-   **Pros**:
    -   **Resilience**: Individual batch failures don't crash the whole sync.
    -   **Scalability**: Can handle products with thousands of variations without timeouts.
    -   **Observability**: Detailed logging and metrics at each stage.
    -   **Non-blocking**: User doesn't wait for HTTP response.
-   **Cons**:
    -   **Complexity**: More moving parts (jobs, queues, batches).
    -   **Latency**: Full sync might take slightly longer due to queue overhead (though usually faster due to parallelism).

---

## 2. Migration Guide

### Transitioning Existing Syncs
No database migration is strictly required for the data structure, but operational changes are needed.

### Handling In-Progress Syncs
1.  **Stop Old Processes**: Ensure no old synchronous sync scripts are running.
2.  **Drain Queues**: Allow existing queues to empty before deploying the new code if possible.
3.  **Deploy**: Push the new job classes and service updates.
4.  **Restart Workers**: `php artisan queue:restart` is mandatory.

### Store ID Migration
If you are migrating from a legacy system where Store IDs were handled differently:
-   Ensure `vendor_connected_stores` table has valid `store_identifier` and tokens.
-   The new jobs rely on `VendorDesignTemplateStore` IDs. Ensure these records exist.

### Rollback Procedure
If the new sync fails catastrophically:
1.  Revert the code deployment.
2.  Restart queue workers.
3.  Manually reset `sync_status` of affected stores to `pending` in the database:
    ```sql
    UPDATE vendor_design_template_stores SET sync_status = 'pending' WHERE sync_status = 'syncing';
    ```

---

## 3. Runbook

### Monitoring
We use structured logging and metrics.

-   **Logs**: Check `storage/logs/laravel.log`. Search for `[WooCommerce Sync]`.
    -   `job`: Name of the job (e.g., `SyncWooVariationBatchJob`).
    -   `store_id`: ID of the store being synced.
    -   `batch_id`: ID of the variation batch.
-   **Metrics**:
    -   `woo_sync_job_dispatched`: Count of jobs started.
    -   `woo_sync_batch_duration`: Time taken per batch.
    -   `woo_sync_job_failed`: Count of failures.

### Common Issues & Troubleshooting

#### 1. "Job Stuck in Pending"
-   **Cause**: Queue workers are not running or are stuck.
-   **Fix**: Check supervisor status. Run `php artisan queue:work --tries=3 --timeout=120`.

#### 2. "Rate Limit Exceeded"
-   **Cause**: Too many batches running in parallel hitting WooCommerce API limits.
-   **Fix**:
    -   Reduce batch size in `WooCommerceDataService::BATCH_SIZE`.
    -   Adjust queue worker concurrency.

#### 3. "Duplicate Variations"
-   **Cause**: Race condition or retry without idempotency.
-   **Fix**: The new system uses Cache Locks (`Cache::lock`) in `SyncWooVariationBatchJob`. Ensure your cache driver (Redis) is working correctly.

#### 4. Manually Retry Failed Syncs
If a batch fails, Laravel's queue will retry it 3 times (configured in Job). If it fails permanently:
1.  Identify the failed `vendor_design_template_store_id`.
2.  Trigger a new sync via the API or command line:
    ```php
    $store = VendorDesignTemplateStore::find($id);
    dispatch(new \App\Jobs\WooCommerce\SyncWooBaseProductJob($store));
    ```

### Webhook Security
-   **Signature Validation**: All incoming webhooks are validated against `woocommerce.webhook_secret`.
-   **Rate Limiting**: `woo-webhooks` limiter allows 100 requests/minute per IP.
