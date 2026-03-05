<?php

namespace App\Events\Shipping;

use App\Models\Sales\Order\Shipment\SalesOrderShipmentTrackingLog;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TrackingUpdated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public SalesOrderShipmentTrackingLog $trackingLog
    ) {}
}
