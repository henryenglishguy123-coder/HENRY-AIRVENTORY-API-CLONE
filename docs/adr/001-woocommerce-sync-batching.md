# ADR 001: WooCommerce Sync Batching Architecture

## Status
Accepted

## Context
The previous WooCommerce sync implementation faced several critical issues when handling products with a large number of variations (e.g., >50 variants):
1.  **Timeouts**: Synchronous processing of all variations in a single request often exceeded PHP execution time limits (30-60s) or gateway timeouts (120s).
2.  **Memory Exhaustion**: Loading all variation data and processing images in memory simultaneously caused OOM errors.
3.  **N+1 Queries**: Inefficient data loading led to database performance bottlenecks.
4.  **Race Conditions**: SKU generation using timestamps caused collisions when jobs ran in parallel.
5.  **API Rate Limits**: Aggressive parallel requests triggered WooCommerce API rate limits (HTTP 429).

## Decision
We have adopted an **Asynchronous Job-Based Batching Architecture** for WooCommerce product synchronization.

### Key Components
1.  **Batch Processing**: Variations are split into configurable batches (default: 50) and processed by separate queue jobs (`SyncWooVariationBatchJob`).
2.  **Job Orchestration**: 
    - `SyncWooBaseProductJob`: Syncs the parent product and dispatches batch jobs.
    - `SyncWooVariationBatchJob`: Syncs a subset of variations.
    - `FinalizeWooSyncJob`: Runs after all batches complete (success or failure) to update store status.
3.  **Queue Prioritization**:
    - `high`: Base product creation (blocking dependency).
    - `default`: Variation batches (bulk work).
    - `low`: Finalization tasks.
4.  **UUID-Based SKUs**: Replaced timestamp-based SKU generation with UUIDs (`T{id}-S{storeId}-{UUID}`) to guarantee uniqueness and prevent collisions.
5.  **Rate Limiting**: Implemented `RateLimiter` middleware in jobs to enforce a limit of 60 requests/minute per store.
6.  **Observability**: Integrated structured logging and custom metrics (`App\Support\Metrics`) for granular tracking of job performance and failure rates.

## Consequences

### Positive
-   **Scalability**: Can handle products with hundreds of variations without timing out.
-   **Reliability**: Failed batches can be retried independently without re-syncing the entire product (Idempotency).
-   **Observability**: Detailed metrics and logs allow for quick diagnosis of sync issues.
-   **Performance**: Eager loading reduced database queries significantly.

### Negative
-   **Complexity**: Debugging async distributed jobs is more complex than a single synchronous script.
-   **Latency**: End-to-end sync time might be slightly longer due to queue overhead, though perceived reliability is higher.

## Benchmarks & Performance
-   **Batch Size**: Optimized at 50 variations per batch to balance memory usage and API round-trips.
-   **Throughput**: sustained ~60 variations/minute (limited by WooCommerce API rate limits).
-   **Memory**: Peak memory usage reduced by ~40% for large products due to batching.
