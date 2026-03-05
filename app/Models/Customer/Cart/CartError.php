<?php

namespace App\Models\Customer\Cart;

use App\Models\Factory\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CartError extends Model
{
    protected $fillable = [
        'cart_id',
        'sku',
        'factory_id',
        'error_code',
        'error_message',
    ];

    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    public function factory(): BelongsTo
    {
        return $this->belongsTo(Factory::class);
    }
}
