<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Console Commands
|--------------------------------------------------------------------------
*/

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');


/*
|--------------------------------------------------------------------------
| Scheduled Tasks
|--------------------------------------------------------------------------
*/

$timezone = config('app.timezone', 'UTC');

/**
 * WooCommerce connection health check
 */
Schedule::command('woocommerce:check-connections')
    ->dailyAt('00:30')
    ->timezone($timezone)
    ->withoutOverlapping()
    ->onOneServer()
    ->runInBackground()
    ->description('Check WooCommerce store connections');


/**
 * Daily Vendor Report
 */
Schedule::command('report:daily-vendor')
    ->dailyAt('01:00')
    ->timezone($timezone)
    ->withoutOverlapping()
    ->onOneServer()
    ->evenInMaintenanceMode()
    ->runInBackground()
    ->description('Generate daily vendor report');


/**
 * Missing Order Sync (Config Driven)
 */
if (config('order_sync.enabled', true)) {
    Schedule::command('orders:sync-missing')
        ->cron(config('order_sync.schedule', '0 2 * * *'))
        ->timezone($timezone)
        ->withoutOverlapping()
        ->onOneServer()
        ->runInBackground()
        ->description('Sync missing paid orders from connected stores');
}
