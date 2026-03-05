<?php

namespace App\Models\Sales\Order\Item;

use App\Models\Sales\Order\Branding\SalesOrderBranding;
use App\Models\Sales\Order\SalesOrder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SalesOrderItem extends Model
{
    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($item) {
            // Ensure minimum weight if zero/negative to prevent downstream logistics issues
            if ($item->unit_weight <= 0) {
                $item->unit_weight = 0.0001; 
            }

            // Optional: Log if price is suspiciously low for commercial items
            if ($item->row_price > 0 && $item->row_price < 0.01) {
                \Illuminate\Support\Facades\Log::debug("SalesOrderItem: Low row_price detected for SKU {$item->sku}: {$item->row_price}");
            }
        });
    }

    protected $table = 'sales_order_items';

    protected $fillable = [
        'order_id',
        'product_id',
        'variant_id',
        'template_id',

        // Product snapshot
        'product_name',
        'catalog_name',
        'sku',

        // Weight
        'weight_unit',
        'unit_weight',

        // Pricing
        'factory_price',
        'margin_price',

        // Printing
        'printing_description',
        'printing_cost',

        'branding_cost',
        'branding_cost_inc_margin',

        // Unit pricing
        'row_price',
        'row_price_inc_margin',

        // Quantity & tax
        'qty',
        'tax_rate',

        // Subtotals
        'subtotal',
        'subtotal_tax',
        'subtotal_inc_margin',
        'subtotal_inc_margin_tax',

        // Grand totals
        'grand_total',
        'grand_total_inc_margin',
    ];

    protected $casts = [
        // Weight
        'unit_weight' => 'decimal:4',

        // Pricing
        'factory_price' => 'decimal:4',
        'margin_price' => 'decimal:4',
        'printing_cost' => 'decimal:4',
        'branding_cost' => 'decimal:4',
        'branding_cost_inc_margin' => 'decimal:4',

        'printing_description' => 'array',

        // Unit pricing
        'row_price' => 'decimal:4',
        'row_price_inc_margin' => 'decimal:4',

        // Quantity & tax
        'qty' => 'integer',
        'tax_rate' => 'decimal:2',

        // Subtotals
        'subtotal' => 'decimal:4',
        'subtotal_tax' => 'decimal:4',
        'subtotal_inc_margin' => 'decimal:4',
        'subtotal_inc_margin_tax' => 'decimal:4',

        // Grand totals
        'grand_total' => 'decimal:4',
        'grand_total_inc_margin' => 'decimal:4',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function order(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class, 'order_id');
    }

    public function designs(): HasMany
    {
        return $this->hasMany(
            SalesOrderItemDesign::class,
            'order_item_id'
        );
    }

    public function options(): HasMany
    {
        return $this->hasMany(
            SalesOrderItemOption::class,
            'order_item_id'
        );
    }

    public function branding(): HasOne
    {
        return $this->hasOne(SalesOrderBranding::class, 'order_item_id');
    }

    public function shipmentItems(): HasMany
    {
        return $this->hasMany(
            \App\Models\Sales\Order\Shipment\SalesOrderShipmentItem::class,
            'sales_order_item_id'
        );
    }
}
