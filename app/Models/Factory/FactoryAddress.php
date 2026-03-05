<?php

namespace App\Models\Factory;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FactoryAddress extends Model
{
    use HasFactory;

    protected $fillable = [
        'factory_id',
        'type',
        'address',
        'country_id',
        'state_id',
        'city',
        'postal_code',
    ];

    public function factory()
    {
        return $this->belongsTo(Factory::class, 'factory_id');
    }
}
