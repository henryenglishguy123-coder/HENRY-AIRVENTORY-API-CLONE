<?php

namespace App\Models\Customer\Cart;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CartItemOption extends Model
{
    protected $fillable = [
        'cart_item_id',
        'option_id',
        'option_code',
        'option_value',
    ];

    public function cartItem(): BelongsTo
    {
        return $this->belongsTo(CartItem::class);
    }
}
