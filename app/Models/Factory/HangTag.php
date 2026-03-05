<?php

namespace App\Models\Factory;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HangTag extends Model
{
    use HasFactory;
    protected $table = 'factory_hang_tags';

    protected $fillable = [
        'factory_id',
        'front_price',
        'back_price',
        'is_active',
    ];

    protected $casts = [
        'front_price' => 'decimal:2',
        'back_price' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function factory(): BelongsTo
    {
        return $this->belongsTo(Factory::class, 'factory_id');
    }
}
