<?php

namespace App\Models\Sales\Order\Branding;

use App\Models\Customer\Branding\VendorDesignBranding;
use App\Models\Factory\HangTag;
use App\Models\Factory\PackagingLabel;
use App\Models\Sales\Order\Item\SalesOrderItem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesOrderBranding extends Model
{
    protected $table = 'sales_order_brandings';

    protected $fillable = [
        'order_item_id',
        'packaging_label_id',
        'hang_tag_id',
        'applied_packaging_label_id',
        'applied_hang_tag_id',
        'packaging_base_price',
        'packaging_margin_price',
        'hang_tag_base_price',
        'hang_tag_margin_price',
        'qty',
        'packaging_total',
        'hang_tag_total',
    ];

    protected $casts = [
        'packaging_base_price' => 'decimal:4',
        'packaging_margin_price' => 'decimal:4',
        'hang_tag_base_price' => 'decimal:4',
        'hang_tag_margin_price' => 'decimal:4',
        'qty' => 'integer',
        'packaging_total' => 'decimal:4',
        'hang_tag_total' => 'decimal:4',
    ];

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(SalesOrderItem::class, 'order_item_id');
    }

    public function packagingLabel(): BelongsTo
    {
        return $this->belongsTo(PackagingLabel::class, 'packaging_label_id');
    }

    public function hangTag(): BelongsTo
    {
        return $this->belongsTo(HangTag::class, 'hang_tag_id');
    }

    public function appliedPackagingLabel(): BelongsTo
    {
        return $this->belongsTo(VendorDesignBranding::class, 'applied_packaging_label_id');
    }

    public function appliedHangTag(): BelongsTo
    {
        return $this->belongsTo(VendorDesignBranding::class, 'applied_hang_tag_id');
    }
}
