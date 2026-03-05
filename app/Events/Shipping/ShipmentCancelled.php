<?php

namespace App\Events\Shipping;

use App\Models\Sales\Order\Shipment\SalesOrderShipment;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ShipmentCancelled
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public SalesOrderShipment $shipment
    ) {}
}
