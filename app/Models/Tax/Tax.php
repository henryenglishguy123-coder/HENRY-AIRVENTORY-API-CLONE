<?php

namespace App\Models\Tax;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tax extends Model
{
    protected $table = 'taxes';

    protected $fillable = [
        'code',   // GST, VAT, SALES
        'name',
        'status',
    ];

    protected $casts = [
        'status' => 'boolean',
    ];

    /**
     * Tax rules associated with this tax
     */
    public function rules(): HasMany
    {
        return $this->hasMany(TaxRule::class, 'tax_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 1);
    }
}
