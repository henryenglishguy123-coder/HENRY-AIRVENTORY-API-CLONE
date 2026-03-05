<?php

namespace App\Models\Payment;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PaymentSetting extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'payment_settings';

    /**
     * Mass assignable attributes
     */
    protected $fillable = [
        'payment_method',
        'title',
        'description',
        'app_id',
        'app_secret',
        'logo',
        'is_live',
        'is_active',
    ];

    /**
     * Attribute casting
     */
    protected $casts = [
        'is_live' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * Auto-append computed attributes
     */
    protected $appends = [
        'status_label',
        'environment_label',
    ];

    /* -----------------------------------------------------------------
     | Accessors
     | -----------------------------------------------------------------
     */

    /**
     * Get human-readable active status
     */
    public function getStatusLabelAttribute(): string
    {
        return $this->is_active ? __('Active') : __('Inactive');
    }

    /**
     * Get environment label (Live / Test)
     */
    public function getEnvironmentLabelAttribute(): string
    {
        return $this->is_live ? __('Live') : __('Test');
    }

    /* -----------------------------------------------------------------
     | Scopes
     | -----------------------------------------------------------------
     */

    /**
     * Scope only active payment methods
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope only live payment methods
     */
    public function scopeLive($query)
    {
        return $query->where('is_live', true);
    }

    /* -----------------------------------------------------------------
     | Helpers / Static Methods
     | -----------------------------------------------------------------
     */

    /**
     * Check if a payment method exists
     */
    public static function paymentMethodExists(string $paymentMethod): bool
    {
        return self::where('payment_method', $paymentMethod)->exists();
    }

    /**
     * Get all active & live payment methods
     */
    public static function getActiveLivePaymentMethods()
    {
        return self::query()
            ->active()
            ->live()
            ->get();
    }
}
