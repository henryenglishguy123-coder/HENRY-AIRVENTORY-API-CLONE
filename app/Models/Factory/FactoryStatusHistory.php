<?php

namespace App\Models\Factory;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FactoryStatusHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'factory_id',
        'admin_id',
        'status_type',
        'old_status',
        'new_status',
        'reason',
    ];

    public function factory()
    {
        return $this->belongsTo(\App\Models\Factory\Factory::class);
    }
}
