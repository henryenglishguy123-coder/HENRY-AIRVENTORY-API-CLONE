<?php

namespace App\Events\Order;

use App\Models\Sales\Order\SalesOrder;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderStatusUpdated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public SalesOrder $order,
        public string $oldStatus,
        public string $newStatus
    ) {}
}
