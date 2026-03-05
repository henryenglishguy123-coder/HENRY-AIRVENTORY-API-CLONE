<?php

namespace App\Models\Sales\Order;

use App\Models\Sales\Order\Address\SalesOrderAddress;
use App\Models\Sales\Order\Item\SalesOrderItem;
use App\Models\Sales\Order\Payment\SalesOrderPayment;
use App\Models\Sales\Order\SalesOrderStatusHistory;
use App\Models\Sales\Order\Shipment\SalesOrderShipment;
use App\Observers\SalesOrderObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[ObservedBy(SalesOrderObserver::class)]

class SalesOrder extends Model
{
    use SoftDeletes;

    protected $table = 'sales_orders';

    protected $fillable = [
        'order_number',
        'factory_id',
        'customer_id',
        'cart_id',
        'shipping_method',
        'payment_method',
        'tax_description',

        // Base totals
        'base_subtotal_before_discount',
        'base_subtotal',
        'base_subtotal_tax',
        'base_subtotal_inc_margin_before_discount',
        'base_subtotal_inc_margin',
        'base_subtotal_tax_inc_margin',
        'base_total',
        'base_total_inc_margin',

        // Shipping
        'shipping_subtotal',
        'shipping_subtotal_tax',
        'shipping_total',

        // Discounts
        'discount_description',
        'base_discount',
        'base_discount_inc_margin',

        // Grand totals
        'grand_subtotal',
        'grand_subtotal_tax',
        'grand_subtotal_inc_margin',
        'grand_subtotal_tax_inc_margin',
        'grand_total',
        'grand_total_inc_margin',

        // Status & meta
        'order_status',
        'payment_status',
        'remote_ip',
        'delivery_date',
    ];

    protected $casts = [
        // Monetary values
        'base_subtotal_before_discount' => 'decimal:4',
        'base_subtotal' => 'decimal:4',
        'base_subtotal_tax' => 'decimal:4',
        'base_subtotal_inc_margin_before_discount' => 'decimal:4',
        'base_subtotal_inc_margin' => 'decimal:4',
        'base_subtotal_tax_inc_margin' => 'decimal:4',
        'base_total' => 'decimal:4',
        'base_total_inc_margin' => 'decimal:4',

        'shipping_subtotal' => 'decimal:4',
        'shipping_subtotal_tax' => 'decimal:4',
        'shipping_total' => 'decimal:4',

        'base_discount' => 'decimal:4',
        'base_discount_inc_margin' => 'decimal:4',

        'grand_subtotal' => 'decimal:4',
        'grand_subtotal_tax' => 'decimal:4',
        'grand_subtotal_inc_margin' => 'decimal:4',
        'grand_subtotal_tax_inc_margin' => 'decimal:4',
        'grand_total' => 'decimal:4',
        'grand_total_inc_margin' => 'decimal:4',

        // Dates
        'delivery_date' => 'datetime',
    ];

    public function scopeDateBetween($query, ?string $start, ?string $end)
    {
        if ($start) {
            $query->whereDate('created_at', '>=', $start);
        }
        if ($end) {
            $query->whereDate('created_at', '<=', $end);
        }

        return $query;
    }

    public function scopeStatus($query, ?string $status)
    {
        if ($status === null || $status === '') {
            return $query;
        }

        return $query->where('order_status', $status);
    }

    public function scopePaymentStatus($query, ?string $paymentStatus)
    {
        if ($paymentStatus === null || $paymentStatus === '') {
            return $query;
        }

        return $query->where('payment_status', $paymentStatus);
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function items(): HasMany
    {
        return $this->hasMany(
            SalesOrderItem::class,
            'order_id'
        );
    }

    public function factory()
    {
        return $this->belongsTo(\App\Models\Factory\Factory::class, 'factory_id');
    }

    public function customer()
    {
        return $this->belongsTo(\App\Models\Customer\Vendor::class, 'customer_id');
    }

    /**
     * Anonymize the remote IP address before saving.
     */
    public function setRemoteIpAttribute(?string $value): void
    {
        if (empty($value)) {
            $this->attributes['remote_ip'] = null;

            return;
        }
        if (filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $parts = explode('.', $value);
            array_pop($parts);
            $parts[] = '0';
            $this->attributes['remote_ip'] = implode('.', $parts);

            return;
        }
        if (filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $packed = inet_pton($value);
            if ($packed === false) {
                $this->attributes['remote_ip'] = null;

                return;
            }
            $mask = str_repeat(chr(0xFF), 6).str_repeat(chr(0x00), 10);
            $masked = $packed & $mask;
            $this->attributes['remote_ip'] = inet_ntop($masked);

            return;
        }
        $key = (string) config('app.key');
        $this->attributes['remote_ip'] = 'anonymized-'.hash_hmac('sha256', $value, $key);
    }

    /*
    |--------------------------------------------------------------------------
    | Address & Payment Relationships
    |--------------------------------------------------------------------------
    */

    public function addresses(): HasMany
    {
        return $this->hasMany(
            SalesOrderAddress::class,
            'order_id'
        );
    }

    public function billingAddress()
    {
        return $this->hasOne(SalesOrderAddress::class, 'order_id')->where('address_type', 'billing');
    }

    public function shippingAddress()
    {
        return $this->hasOne(SalesOrderAddress::class, 'order_id')->where('address_type', 'shipping');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(
            SalesOrderPayment::class,
            'order_id'
        );
    }

    public function errors(): HasMany
    {
        return $this->hasMany(
            SalesOrderError::class,
            'order_id'
        );
    }

    public function sourceInfo()
    {
        return $this->hasOne(SalesOrderSource::class, 'order_id');
    }

    public function shipments(): HasMany
    {
        return $this->hasMany(
            SalesOrderShipment::class,
            'sales_order_id'
        );
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(
            SalesOrderStatusHistory::class,
            'order_id'
        );
    }

    public function messages(): HasMany
    {
        return $this->hasMany(
            Message::class,
            'order_id'
        );
    }
}
