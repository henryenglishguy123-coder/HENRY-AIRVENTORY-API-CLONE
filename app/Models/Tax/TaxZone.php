<?php

namespace App\Models\Tax;

use App\Models\Location\Country;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TaxZone extends Model
{
    protected $table = 'tax_zones';

    protected $fillable = [
        'name',
        'country_id',
        'state_code',
        'postal_code_start',
        'postal_code_end',
        'status',
    ];

    protected $casts = [
        'status' => 'boolean',
    ];

    /**
     * Country this tax zone belongs to
     */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'country_id');
    }

    /**
     * Tax rules applied to this zone
     */
    public function rules(): HasMany
    {
        return $this->hasMany(TaxRule::class, 'tax_zone_id');
    }
}
