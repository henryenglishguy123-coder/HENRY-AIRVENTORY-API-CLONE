<?php

namespace App\Models\Sales\Order;

use Illuminate\Database\Eloquent\Model;

class OrderSequence extends Model
{
    protected $table = 'order_sequences';

    protected $fillable = [
        'prefix',
        'current_value',
        'last_order_number',
    ];

    protected $casts = [
        'last_order_number' => 'string',
    ];

    /**
     * This table should always have timestamps
     */
    public $timestamps = true;
}
