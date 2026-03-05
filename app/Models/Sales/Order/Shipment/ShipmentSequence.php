<?php

namespace App\Models\Sales\Order\Shipment;

use Illuminate\Database\Eloquent\Model;

class ShipmentSequence extends Model
{
    protected $table = 'shipment_sequences';

    protected $fillable = [
        'prefix',
        'current_value',
        'last_shipment_number',
    ];

    /**
     * This table should always have timestamps
     */
    public $timestamps = true;
}
