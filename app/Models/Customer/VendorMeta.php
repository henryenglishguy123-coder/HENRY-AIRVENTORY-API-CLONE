<?php

namespace App\Models\Customer;

use Illuminate\Database\Eloquent\Model;

class VendorMeta extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'vendor_id',
        'key',
        'value',
        'type',
    ];
}
