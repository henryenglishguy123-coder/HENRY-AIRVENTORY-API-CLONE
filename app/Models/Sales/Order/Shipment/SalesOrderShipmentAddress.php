<?php

namespace App\Models\Sales\Order\Shipment;

use Illuminate\Database\Eloquent\Model;

class SalesOrderShipmentAddress extends Model
{
    protected $table = 'sales_order_shipment_addresses';

    protected $fillable = [
        'sales_order_shipment_id',
        'address_type',
        'first_name',
        'last_name',
        'phone',
        'email',
        'address_line_1',
        'address_line_2',
        'city',
        'state_id',
        'state',
        'postal_code',
        'country_id',
        'country',
    ];

    protected $casts = [
        'state_id' => 'integer',
        'country_id' => 'integer',
    ];

    public function shipment()
    {
        return $this->belongsTo(SalesOrderShipment::class, 'sales_order_shipment_id');
    }

    public function countryData(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\Location\Country::class, 'country_id');
    }

    public function stateData(): \Illuminate\Database\Eloquent\Relations\BelongsTo
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
