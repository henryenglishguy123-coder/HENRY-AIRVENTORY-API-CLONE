<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;

class Metrics
{
    /**
     * Increment a counter metric.
     */
    public static function increment(string $key, int $value = 1, array $tags = []): void
    {
        // In a real environment, this would send data to StatsD, Prometheus, or Datadog.
        // For now, we log it with a specific prefix for easy filtering.
        Log::info("METRIC_INC: {$key}", [
            'value' => $value,
            'tags' => $tags,
        ]);
    }

    /**
     * Record a histogram value (e.g., duration).
     */
    public static function histogram(string $key, float $value, array $tags = []): void
    {
        Log::info("METRIC_HIST: {$key}", [
            'value' => $value,
            'tags' => $tags,
        ]);
    }
}
