<?php

namespace App\Policies\Sales\Order;

use App\Models\Customer\Vendor;
use App\Models\Sales\Order\SalesOrder;
use Illuminate\Auth\Access\HandlesAuthorization;

class SalesOrderPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the customer can reorder the order.
     */
    public function reorder(Vendor $customer, SalesOrder $order): bool
    {
        return (int) $order->customer_id === (int) $customer->id;
    }
}
