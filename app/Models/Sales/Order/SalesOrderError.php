<?php

namespace App\Models\Sales\Order;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SalesOrderError extends Model
{
    use HasFactory;

    protected $table = 'sales_order_errors';

    protected $fillable = [
        'order_id',
        'error_key',
        'error_description',
    ];

    public function order()
    {
        return $this->belongsTo(SalesOrder::class, 'order_id');
    }
}
