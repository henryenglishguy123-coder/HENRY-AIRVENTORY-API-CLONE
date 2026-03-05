<?php

namespace App\Models\PrintingTechnique;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PrintingTechnique extends Model
{
    use SoftDeletes;
    protected $table = 'printing_techniques';

    protected $fillable = [
        'name',
        'status',
    ];

    public function scopeActive($query)
    {
        return $query->where('status', 1);
    }
}
