<?php

namespace App\Models\Customer\Cart;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CartAddress extends Model
{
    protected $fillable = [
        'cart_id',
        'first_name',
        'last_name',
        'email',
        'phone',
        'address_line_1',
        'address_line_2',
        'city',
        'state',
        'state_id',
        'postal_code',
        'country',
        'country_id',
    ];

    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }
}
