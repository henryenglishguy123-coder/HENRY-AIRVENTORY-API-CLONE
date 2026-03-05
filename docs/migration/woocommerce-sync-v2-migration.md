# WooCommerce Sync V2 Migration Guide

## Overview
This guide covers the migration process from the legacy synchronous WooCommerce sync to the new V2 asynchronous batching architecture.

## 1. Handling Existing Syncs
### In-Flight Jobs
-   **Scenario**: Jobs running during deployment.
-   **Action**: 
    -   The new code is backward compatible with the data structure. 
    -   **Note**: `SyncVendorTemplateToStoreJob` has been removed. Any existing instances of this job in the queue will fail with a "Class not found" error.
    -   Recommendation: Drain the queue before deployment. Failed jobs will need to be discarded.

### Zombie Jobs
-   **Scenario**: Jobs stuck in `syncing` state due to previous timeouts.
-   **Action**: 
    -   Run a database update to reset stuck jobs:
        ```sql
        UPDATE vendor_design_template_stores 
        SET sync_status = 'failed', sync_error = 'Reset during migration' 
        WHERE sync_status = 'syncing' AND updated_at < NOW() - INTERVAL 1 HOUR;
        ```

## 2. Store ID Migration
### Context
We introduced a stricter resolution for store IDs. The system now supports resolving stores by both numeric ID and string identifier (e.g., for legacy imports).

### Resolution Logic
The `VendorConnectedStore::resolveId($identifier)` method handles this transparently:
-   If `$identifier` is numeric -> Uses it as ID.
-   If `$identifier` is string -> Lookups `store_identifier` column.

### Verification Steps
1.  Check logs for `ModelNotFoundException` related to `VendorConnectedStore`.
2.  Verify that legacy store mappings in the database are correct.

## 3. Rollback Procedure
If critical issues are encountered:

1.  **Revert Codebase**: Revert to the previous commit.
2.  **Database**: No schema changes were made that require rollback.
3.  **SKU Format**: 
    -   Products synced with V2 will have UUID-based SKUs (e.g., `...-a1b2c3d4`).
    -   **Note**: These SKUs are valid in WooCommerce and **do not** need to be reverted. The legacy system will treat them as standard strings.
4.  **Queue**: Clear the `high`, `default`, and `low` queues if they contain V2-specific job payloads that legacy code cannot handle (though payloads are largely compatible).

## 4. Configuration Check
Ensure your `.env` or `config/woocommerce.php` has the following settings tuned:
```php
// config/woocommerce.php
'sync' => [
    'variation_batch_size' => 50, // Default
]
```
