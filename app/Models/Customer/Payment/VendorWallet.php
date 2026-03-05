<?php

namespace App\Models\Customer\Payment;

use App\Models\Customer\Vendor;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VendorWallet extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'vendor_wallets';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'vendor_id',
        'balance',
    ];

    /**
     * Get the vendor associated with the wallet.
     */
    public function vendor()
    {
        return $this->belongsTo(Vendor::class, 'vendor_id');
    }

    /**
     * Get the transactions for the wallet.
     */
    public function transactions()
    {
        return $this->hasMany(VendorWalletTransaction::class, 'wallet_id');
    }
}
