<?php

namespace App\Models\Customer;

use Illuminate\Database\Eloquent\Model;

class VendorUserCart extends Model
{
    protected $fillable = [
        'vendor_user_id',
        'shipping_address',
        'first_name',
        'last_name',
        'email',
        'phone',
        'city',
        'state_id',
        'zip_code',
        'country_id',
        'status',
    ];

    public function cartItems()
    {
        return $this->hasMany(VendorUserCartItem::class, 'cart_id', 'id');
    }
}
