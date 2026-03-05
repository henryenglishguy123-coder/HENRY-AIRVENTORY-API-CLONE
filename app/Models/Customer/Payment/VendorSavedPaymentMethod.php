<?php

namespace App\Models\Customer\Payment;

use App\Models\Customer\Vendor;
use Illuminate\Database\Eloquent\Model;

class VendorSavedPaymentMethod extends Model
{
    protected $table = 'vendor_saved_payment_method';

    protected $fillable = [
        'vendor_id',
        'payment_method',
        'saved_card_id',
        'card_type',
        'card_last_digit',
        'is_default',
    ];

    protected $casts = [
        'is_default' => 'boolean',
    ];

    public function customer()
    {
        return $this->belongsTo(Vendor::class, 'vendor_id', 'id');
    }
}
