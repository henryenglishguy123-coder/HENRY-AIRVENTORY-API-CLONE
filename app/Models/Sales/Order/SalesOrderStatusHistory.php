<?php

namespace App\Models\Sales\Order;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sales\Order\Shipment\SalesOrderShipment;
use App\Models\Shipping\ShippingPartner;

class SalesOrderStatusHistory extends Model
{
    protected $table = 'sales_order_status_history';
    
    const UPDATED_AT = null;

    protected $fillable = [
        'order_id',
        'from_status',
        'to_status',
        'reason',
        'source',
        'shipping_partner_id',
        'shipment_id',
        'admin_id',
        'full_payload',
    ];

    protected $casts = [
        'full_payload' => 'json',
    ];

    public function order()
    {
        return $this->belongsTo(SalesOrder::class, 'order_id');
    }

    public function shipment()
    {
        return $this->belongsTo(SalesOrderShipment::class, 'shipment_id');
    }

    public function shippingPartner()
    {
        return $this->belongsTo(ShippingPartner::class, 'shipping_partner_id');
    }
}
