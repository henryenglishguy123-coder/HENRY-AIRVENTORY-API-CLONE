<?php

namespace App\Models\Tax;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaxRule extends Model
{
    protected $table = 'tax_rules';

    protected $fillable = [
        'tax_id',
        'tax_zone_id',
        'rate',
        'priority',
        'status',
    ];

    protected $casts = [
        'rate' => 'decimal:2',
        'priority' => 'integer',
        'status' => 'boolean',
    ];

    /**
     * Tax type (GST, VAT, etc.)
     */
    public function tax(): BelongsTo
    {
        return $this->belongsTo(Tax::class, 'tax_id');
    }

    /**
     * Zone where this rule applies
     */
    public function zone(): BelongsTo
    {
        return $this->belongsTo(TaxZone::class, 'tax_zone_id');
    }

    /**
     * Scope a query to only include active tax rules.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 1);
    }
}
