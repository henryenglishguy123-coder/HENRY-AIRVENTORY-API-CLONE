<?php

namespace App\Actions\Shipping;

use App\Enums\Order\OrderStatus;
use App\Jobs\Shipping\DispatchShipmentCreationJob;
use App\Models\Sales\Order\SalesOrder;
use Exception;
use Illuminate\Support\Facades\Auth;

class ShipFullOrderAction
{
    /**
     * Executes the 'Ship Now' flow for a given order.
     *
     * @throws Exception
     */
    public static function run(SalesOrder $order): void
    {
        // 1. Order must be Confirmed
        if ($order->order_status !== OrderStatus::Confirmed->value) {
            throw new Exception("Order must be Confirmed to proceed with shipping. Current status: {$order->order_status}");
        }

        // 2. Order must be factory assigned
        if (! $order->factory_id) {
            throw new Exception('Cannot ship an order without an assigned factory.');
        }

        // 3. Factory record must exist
        $factory = $order->factory;
        if (! $factory) {
            throw new Exception('Factory record not found for this order.');
        }

        // 4. Factory must have exactly one shipping partner assigned
        $activePartnersCount = $factory->shippingPartners()->count();
        if ($activePartnersCount === 0) {
            throw new Exception('Factory assigned to this order does not have any shipping partner.');
        } elseif ($activePartnersCount > 1) {
            throw new Exception('Factory has multiple shipping partners assigned. Only one can be active to ship.');
        }

        // 5. Require a valid authenticated admin ID — no hardcoded fallback
        $adminId = Auth::guard('admin_api')->id();
        if (! $adminId) {
            throw new Exception('No authenticated admin found. This action must be performed by an authenticated admin.');
        }

        DispatchShipmentCreationJob::dispatch($order, $adminId);
    }
}
