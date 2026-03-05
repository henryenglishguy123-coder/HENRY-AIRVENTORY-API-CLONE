<?php

namespace App\Models\Factory;

use App\Models\Location\Country;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class FactoryShippingRate extends Model
{
    use SoftDeletes;

    protected $table = 'factory_shipping_rates';

    protected $fillable = [
        'factory_id',
        'country_code',
        'min_qty',
        'price',
        'shipping_title',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'min_qty' => 'integer',
    ];

    public function factory(): BelongsTo
    {
        return $this->belongsTo(Factory::class, 'factory_id');
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'country_code', 'iso2');
    }
}
