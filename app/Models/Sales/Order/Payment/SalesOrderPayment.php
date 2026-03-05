<?php

namespace App\Models\Sales\Order\Payment;

use App\Models\Customer\Vendor;
use App\Models\Sales\Order\SalesOrder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SalesOrderPayment extends Model
{
    use SoftDeletes;

    protected $table = 'sales_order_payments';

    protected $fillable = [
        'vendor_id',
        'order_id',
        'transaction_id',

        // Payment details
        'payment_method',
        'gateway',
        'payment_status',
        'currency_code',

        // Amounts
        'amount',
        'refunded_amount',

        // Gateway & meta
        'gateway_response',
        'paid_at',
        'notes',
    ];

    protected $casts = [
        // Amounts
        'amount' => 'decimal:4',
        'refunded_amount' => 'decimal:4',

        // Status & meta
        'payment_status' => 'string',
        'currency_code' => 'string',

        // JSON & dates
        'gateway_response' => 'array',
        'paid_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function order(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class, 'order_id');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class, 'vendor_id');
    }
}
