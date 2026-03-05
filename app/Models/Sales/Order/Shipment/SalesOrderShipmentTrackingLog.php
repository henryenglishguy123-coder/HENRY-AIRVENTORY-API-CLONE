<?php

namespace App\Models\Sales\Order\Shipment;

use Illuminate\Database\Eloquent\Model;

class SalesOrderShipmentTrackingLog extends Model
{
    protected $table = 'sales_order_shipment_tracking_logs';

    protected $fillable = [
        'shipment_id',
        'status',
        'sub_status',
        'description',
        'location',
        'checkpoint_time',
        'raw_payload',
        'provider',
        'provider_event_id',
    ];

    protected $casts = [
        'checkpoint_time' => 'datetime',
        'raw_payload' => 'array',
    ];

    public function shipment()
    {
        return $this->belongsTo(SalesOrderShipment::class, 'shipment_id');
    }
}
