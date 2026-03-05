<?php

namespace App\Models\Factory;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FactoryMetas extends Model
{
    use HasFactory;

    protected $table = 'factory_metas';

    protected $fillable = [
        'factory_id',
        'key',
        'value',
        'type',
    ];

    public function factory()
    {
        return $this->belongsTo(Factory::class);
    }
}
