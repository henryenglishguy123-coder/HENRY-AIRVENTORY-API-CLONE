<?php

namespace App\Models\Sales\Order\Address;

use App\Models\Location\Country;
use App\Models\Sales\Order\SalesOrder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesOrderAddress extends Model
{
    protected $table = 'sales_order_addresses';

    protected $fillable = [
        'order_id',

        // Customer snapshot
        'first_name',
        'last_name',
        'phone',
        'email',

        // Address
        'address_type', // billing | shipping
        'address_line_1',
        'address_line_2',
        'city',
        'state_id',
        'state',
        'postal_code',
        'country',
        'country_id',
    ];

    protected $casts = [
        'state_id' => 'integer',
        'country_id' => 'integer',
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

    public function countryData(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'country_id');
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'country_id');
    }

    public function stateData(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Location\State::class, 'state_id');
    }

    public function state(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Location\State::class, 'state_id');
    }

    public function getCityStatePostalAttribute(): string
    {
        $parts = array_filter([$this->city ?? '', $this->state ?? '']);
        $line = $parts ? implode(', ', $parts) : '';
        if ($this->postal_code) {
            $line = $line ? $line.' '.$this->postal_code : $this->postal_code;
        }

        return $line;
    }
}
