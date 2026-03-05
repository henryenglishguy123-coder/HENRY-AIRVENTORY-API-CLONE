<?php

namespace App\Models\Sales\Order\Shipment;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sales\Order\SalesOrder;
use App\Models\Sales\Order\Item\SalesOrderItem;

class SalesOrderShipmentItem extends Model
{
    protected $table = 'sales_order_shipment_items';

    public $timestamps = false;

    protected $fillable = [
        'sales_order_shipment_id',
        'sales_order_id',
        'sales_order_item_id',
        'sales_order_item_name',
        'sales_order_item_sku',
        'quantity',
    ];

    public function shipment()
    {
        return $this->belongsTo(SalesOrderShipment::class, 'sales_order_shipment_id');
    }

    public function order()
    {
        return $this->belongsTo(SalesOrder::class, 'sales_order_id');
    }

    public function orderItem()
    {
        return $this->belongsTo(SalesOrderItem::class, 'sales_order_item_id');
    }
}
