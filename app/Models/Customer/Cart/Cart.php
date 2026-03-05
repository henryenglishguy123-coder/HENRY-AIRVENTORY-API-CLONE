<?php

namespace App\Models\Customer\Cart;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Cart extends Model
{
    protected $fillable = [
        'vendor_id',
        'status',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    public function sources(): HasMany
    {
        return $this->hasMany(CartSource::class);
    }

    public function errors(): HasMany
    {
        return $this->hasMany(CartError::class);
    }

    public function totals(): HasOne
    {
        return $this->hasOne(CartTotal::class);
    }

    public function discount(): HasOne
    {
        return $this->hasOne(CartDiscount::class);
    }

    public function address()
    {
        return $this->hasOne(CartAddress::class);
    }
}
