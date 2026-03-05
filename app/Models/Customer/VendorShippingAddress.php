<?php

namespace App\Models\Customer;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class VendorShippingAddress extends Model
{
    use SoftDeletes;

    protected $table = 'vendor_shipping_addresses';

    protected $fillable = [
        'vendor_id',
        'first_name',
        'last_name',
        'email',
        'phone',
        'address',
        'country_id',
        'state_id',
        'city',
        'postal_code',
        'is_default',
        'status',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'status' => 'boolean',
    ];

    public function vendor()
    {
        return $this->belongsTo(Vendor::class, 'vendor_id');
    }
}
