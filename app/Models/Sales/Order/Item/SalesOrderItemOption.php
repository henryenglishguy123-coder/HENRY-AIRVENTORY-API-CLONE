<?php

namespace App\Models\Sales\Order\Item;

use App\Models\Catalog\Attribute\CatalogAttributeOption;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesOrderItemOption extends Model
{
    protected $table = 'sales_order_item_options';

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    protected $fillable = [
        'order_item_id',
        'option_id',
        'option_name',
        'option_value',
    ];

    protected $casts = [
        'order_item_id' => 'integer',
        'option_id' => 'integer',
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

    public function option(): BelongsTo
    {
        return $this->belongsTo(
            CatalogAttributeOption::class,
            'option_id',
            'option_id'
        );
    }
}
