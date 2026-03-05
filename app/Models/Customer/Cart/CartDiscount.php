<?php

namespace App\Models\Customer\Cart;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CartDiscount extends Model
{
    use HasFactory;

    protected $table = 'cart_discounts';

    protected $fillable = [
        'cart_id',
        'source',
        'code',
        'amount',
    ];

    protected $casts = [
        'amount' => 'decimal:4',
    ];

    /**
     * Get the cart that owns the discount.
     */
    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class, 'cart_id');
    }
}
