<?php

namespace App\Models\Customer;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class VendorBillingAddress extends Model
{
    use SoftDeletes;

    protected $table = 'vendor_billing_addresses';

    protected $fillable = [
        'vendor_id',
        'first_name',
        'last_name',
        'company_name',
        'tax_number',
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
