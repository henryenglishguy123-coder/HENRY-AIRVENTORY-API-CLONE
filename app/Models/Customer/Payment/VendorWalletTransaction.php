<?php

namespace App\Models\Customer\Payment;

use App\Models\Customer\Vendor;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VendorWalletTransaction extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'vendor_wallets_transactions';

    public const STATUS_PENDING = 'pending';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'wallet_id',
        'transaction_id',
        'type',
        'amount',
        'status',
        'balance_after',
        'payment_method',
        'description',
        'other_payment_details',
    ];

    /**
     * Get the wallet associated with the transaction.
     */
    public function wallet()
    {
        return $this->belongsTo(VendorWallet::class, 'wallet_id');
    }

    /**
     * Scope a query to filter transactions by type.
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Get the vendor associated with the transaction.
     */
    public function vendor()
    {
        return $this->belongsToThrough(Vendor::class, VendorWallet::class);
    }
}
