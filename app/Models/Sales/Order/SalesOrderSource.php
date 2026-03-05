<?php

namespace App\Models\Sales\Order;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesOrderSource extends Model
{
    protected $fillable = [
        'order_id',
        'platform',
        'source',
        'source_order_id',
        'source_order_number',
        'source_created_at',
        'payload',
    ];

    protected $hidden = [
        'payload',
    ];

    protected $casts = [
        'payload' => 'array',
        'source_created_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class, 'order_id');
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(\App\Models\StoreChannels\StoreChannel::class, 'platform', 'code');
    }
}
