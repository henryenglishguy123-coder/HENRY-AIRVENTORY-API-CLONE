<?php

namespace App\Models\Sales\Order\Item;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesOrderItemDesign extends Model
{
    protected $table = 'sales_order_item_designs';

    protected $fillable = [
        'order_item_id',

        // Layer reference (snapshot)
        'layer_id',
        'layer_name',

        // Images
        'base_image',
        'preview_image',

        // Design data & exports
        'design_data',
        'svg_file',
        'png_file',
        'summary_file',
    ];

    protected $casts = [
        'order_item_id' => 'integer',
        'layer_id' => 'integer',
        'design_data' => 'array',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(
            SalesOrderItem::class,
            'order_item_id'
        );
    }
}
