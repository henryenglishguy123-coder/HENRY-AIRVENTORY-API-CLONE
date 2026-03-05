<?php

namespace App\Models\Customer;

use Illuminate\Database\Eloquent\Model;

class VendorUserCartItem extends Model
{
    protected $fillable = [
        'cart_id',
        'product_id',
        'selected_attributes',
        'quantity',
    ];

    public $timestamps = false;

    public function cart()
    {
        return $this->belongsTo(VendorUserCart::class, 'cart_id');
    }
}
