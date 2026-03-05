<?php

namespace App\Models\Customer\Cart;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CartSource extends Model
{
    protected $fillable = [
        'cart_id',
        'platform',
        'source',
        'source_order_id',
        'source_order_number',
        'source_created_at',
        'payload',
    ];

    protected $casts = [
        'payload' => 'array',
        'source_created_at' => 'datetime',
    ];

    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }
}
