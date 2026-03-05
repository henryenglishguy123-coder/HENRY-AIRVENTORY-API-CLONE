<?php

namespace App\Observers;

use App\Models\Sales\Order\SalesOrder;
use Illuminate\Support\Facades\Cache;

class SalesOrderObserver
{
    /**
     * IMPORTANT: Bulk query-builder operations (e.g. SalesOrder::whereIn(...)->update(...))
     * DO NOT trigger these Eloquent events. Always call `SalesOrderObserver::bumpVersions()`
     * manually if you perform bulk insertions, updates, or deletions.
     */

    /**
     * Helper to manually bump cache versions after bulk queries.
     * @param SalesOrder|null $order Pass the related order if scoping to specific customer/factory.
     */
    public static function bumpVersions(?SalesOrder $order = null): void
    {
        $instance = new self();
        if ($order) {
            $instance->flushCaches($order);
        } else {
            // Global bump
            Cache::put('orders_version:admin_global', now()->timestamp, now()->addDays(30));
        }
    }
    /**
     * Handle the SalesOrder "created" event.
     */
    public function created(SalesOrder $order): void
    {
        $this->flushCaches($order);
    }

    /**
     * Handle the SalesOrder "updated" event.
     */
    public function updated(SalesOrder $order): void
    {
        $this->flushCaches($order);
    }

    /**
     * Handle the SalesOrder "deleted" event.
     */
    public function deleted(SalesOrder $order): void
    {
        $this->flushCaches($order);
    }

    /**
     * Handle the SalesOrder "restored" event.
     */
    public function restored(SalesOrder $order): void
    {
        $this->flushCaches($order);
    }

    /**
     * Handle the SalesOrder "force deleted" event.
     */
    public function forceDeleted(SalesOrder $order): void
    {
        $this->flushCaches($order);
    }

    /**
     * Flush all related order caches.
     */
    protected function flushCaches(SalesOrder $order): void
    {
        $time = now()->timestamp;
        $ttl = now()->addDays(30);

        // 1. Global admin cache (always flushed when an order changes)
        Cache::put('orders_version:admin_global', $time, $ttl);

        // 2. Customer cache
        if ($order->customer_id) {
            Cache::put("orders_version:customer_{$order->customer_id}", $time, $ttl);
        }

        // 3. Factory cache
        if ($order->factory_id) {
            Cache::put("orders_version:factory_{$order->factory_id}", $time, $ttl);
        }
    }
}
