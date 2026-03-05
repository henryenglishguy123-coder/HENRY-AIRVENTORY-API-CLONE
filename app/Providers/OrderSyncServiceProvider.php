<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

class OrderSyncServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Validate order_sync configuration constraints
        $defaultDays = config('order_sync.default_days', 7);
        $maxDays = config('order_sync.max_days', 90);
        
        if ($defaultDays > $maxDays) {
            Log::warning('Invalid order_sync configuration: default_days exceeds max_days', [
                'default_days' => $defaultDays,
                'max_days' => $maxDays,
                'action' => 'Clamping default_days to max_days',
            ]);
            
            // Clamp the value instead of throwing
            config(['order_sync.default_days' => $maxDays]);
        }
    }
}
