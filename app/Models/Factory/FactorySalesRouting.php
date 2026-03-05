<?php

namespace App\Models\Factory;

use App\Models\Location\Country;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FactorySalesRouting extends Model
{
    protected $table = 'factory_sales_routing';

    protected $fillable = [
        'factory_id',
        'country_id',
        'priority',
    ];

    /* =======================
     |  Relationships
     ======================= */

    public function factory(): BelongsTo
    {
        return $this->belongsTo(Factory::class, 'factory_id');
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'country_id');
    }
}
