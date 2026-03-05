<?php

namespace App\Models\Customer\Cart;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CartTotal extends Model
{
    protected $table = 'cart_totals';

    protected $primaryKey = 'cart_id';

    public $incrementing = false;

    protected $fillable = [
        'cart_id',
        'subtotal',
        'subtotal_tax',
        'shipping_amount',
        'shipping_tax',
        'shipping_total',
        'tax_total',
        'discount_total',
        'grand_total',
        'calculated_at',
    ];

    protected $casts = [
        'subtotal' => 'decimal:4',
        'subtotal_tax' => 'decimal:4',
        'shipping_amount' => 'decimal:4',
        'shipping_tax' => 'decimal:4',
        'shipping_total' => 'decimal:4',
        'tax_total' => 'decimal:4',
        'discount_total' => 'decimal:4',
        'grand_total' => 'decimal:4',
        'calculated_at' => 'datetime',
    ];

    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }
}
