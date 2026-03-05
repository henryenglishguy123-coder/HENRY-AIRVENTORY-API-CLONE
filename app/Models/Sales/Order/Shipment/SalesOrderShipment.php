<?php

namespace App\Models\Sales\Order\Shipment;

use App\Models\Sales\Order\SalesOrder;
use App\Models\Sales\Order\SalesOrderStatusHistory;
use Illuminate\Database\Eloquent\Model;

class SalesOrderShipment extends Model
{
    protected $table = 'sales_order_shipments';

    protected $fillable = [
        'sales_order_id',
        'sales_shipment_number',
        'tracking_name',
        'tracking_number',
        'tracking_url',
        'label_type',
        'label_url',
        'waybill_number',
        'external_shipment_id',
        'label_id',
        'status',
        'bar_codes',
        'total_quantity',
        'total_weight',
        'shipping_cost',
        'comment',
    ];

    protected $casts = [
        'total_quantity' => 'decimal:2',
        'total_weight' => 'decimal:4',
        'shipping_cost' => 'decimal:4',
    ];

    public function order()
    {
        return $this->belongsTo(SalesOrder::class, 'sales_order_id');
    }

    public function items()
    {
        return $this->hasMany(SalesOrderShipmentItem::class, 'sales_order_shipment_id');
    }

    public function trackingLogs()
    {
        return $this->hasMany(SalesOrderShipmentTrackingLog::class, 'shipment_id');
    }

    public function addresses()
    {
        return $this->hasMany(SalesOrderShipmentAddress::class, 'sales_order_shipment_id');
    }

    public function billingAddress()
    {
        return $this->hasOne(SalesOrderShipmentAddress::class, 'sales_order_shipment_id')->where('address_type', 'billing');
    }

    public function shippingAddress()
    {
        return $this->hasOne(SalesOrderShipmentAddress::class, 'sales_order_shipment_id')->where('address_type', 'shipping');
    }

    public function statusHistory()
    {
        return $this->hasMany(SalesOrderStatusHistory::class, 'shipment_id');
    }
}
