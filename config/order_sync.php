<?php

// Validate and normalize config values
$defaultDays = max(1, (int) env('ORDER_SYNC_DEFAULT_DAYS', 7));
$maxDays = max(1, (int) env('ORDER_SYNC_MAX_DAYS', 90));
$minHours = max(0, (int) env('ORDER_SYNC_MIN_HOURS', 6));
$lookbackMinutes = max(0, (int) env('ORDER_SYNC_LOOKBACK_MINUTES', 10));
$lockSeconds = max(60, (int) env('ORDER_SYNC_LOCK_SECONDS', 1800));
$chunkSize = max(1, (int) env('ORDER_SYNC_CHUNK_SIZE', 50));
$chunkDelayMs = max(0, (int) env('ORDER_SYNC_CHUNK_DELAY_MS', 100));

// Note: Validation for default_days <= max_days is performed in OrderSyncServiceProvider::boot()
// to avoid disrupting Laravel's bootstrap sequence

return [
    /*
    |--------------------------------------------------------------------------
    | Order Sync Configuration
    |--------------------------------------------------------------------------
    |
    | Configure automatic order syncing behavior
    |
    */

    // Enable/disable automatic order syncing
    'enabled' => filter_var(env('ORDER_SYNC_ENABLED', true), FILTER_VALIDATE_BOOLEAN),

    // Default number of days to look back when syncing
    'default_days' => $defaultDays,

    // Maximum allowed days to sync
    'max_days' => $maxDays,

    // Minimum hours between syncs for same store (prevents too frequent syncing)
    'min_hours_between_syncs' => $minHours,

    // Cron schedule for automatic syncing
    'schedule' => env('ORDER_SYNC_SCHEDULE', '0 2 * * *'), // Default: 2 AM daily

    // Queue to dispatch sync jobs onto
    'queue' => env('ORDER_SYNC_QUEUE', 'low'),

    // Extra minutes to look back to avoid edge misses around boundaries
    'lookback_minutes' => $lookbackMinutes,

    // Global lock to prevent overlapping command runs (seconds)
    'lock_seconds' => $lockSeconds,

    // Chunk size for batch processing orders
    'chunk_size' => $chunkSize,

    // Delay between chunks in milliseconds
    'chunk_delay_ms' => $chunkDelayMs,
];
