# WooCommerce Sync Operations Runbook

## 1. Monitoring
### Log Filters
Monitor logs using the following patterns in your logging system (CloudWatch, DataDog, Laravel Log):
-   **Start/End**: `Sync job started`, `Sync job completed`
-   **Failures**: `SyncWooBaseProductJob failed`, `SyncWooVariationBatchJob failed`, `FinalizeWooSyncJob failed`
-   **Metrics**: `METRIC_INC:`, `METRIC_HIST:`

### Key Metrics to Watch
| Metric Key | Threshold | Description |
|------------|-----------|-------------|
| `woo.sync.jobs.failed` | > 5% | High failure rate indicating API or data issues. |
| `woo.sync.base_product.duration` | > 30s | Base product sync is taking too long (risk of timeout). |
| `woo.sync.finalize.completed` | `partial_failure` count | Indicates batches failed but sync "completed". |

## 2. Troubleshooting
### Common Errors
| Error Message | Cause | Resolution |
|---------------|-------|------------|
| `Rate Limit Exceeded` | WooCommerce API 429 | The built-in rate limiter (60/min) should prevent this. Check if multiple workers are processing the same store. |
| `Timeout` / `504 Gateway Time-out` | Network or Large Batch | Decrease `woocommerce.sync.variation_batch_size` config. |
| `Store channel configuration missing` | Data Integrity | Verify `VendorConnectedStore` exists and has a valid `StoreChannel`. |

### Investigating Stuck Jobs
1.  Check `vendor_design_template_stores` table:
    ```sql
    SELECT * FROM vendor_design_template_stores WHERE sync_status = 'syncing';
    ```
2.  Check the `failed_jobs` table in Laravel:
    ```bash
    php artisan queue:failed
    ```

## 3. Manual Retry & Recovery
### Retry a Failed Job
If a job is in the `failed_jobs` table:
```bash
php artisan queue:retry {id}
```

### Trigger Full Re-sync
To manually trigger a sync for a specific template store:
```php
// Via Tinker
$store = \App\Models\Customer\Designer\VendorDesignTemplateStore::find($id);
\App\Jobs\WooCommerce\SyncWooBaseProductJob::dispatch($store);
```

### Handling Partial Failures
If a sync finishes with `sync_status = 'synced'` but `sync_error` contains "Sync completed with some variation failures":
1.  Check logs for the specific `SyncWooVariationBatchJob` that failed.
2.  You can re-dispatch just the sync job as shown above; the system is idempotent and will update existing variations and create missing ones.
