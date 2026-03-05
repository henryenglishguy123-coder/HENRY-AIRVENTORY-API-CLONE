<?php

namespace App\Models\Factory;

use App\Models\Catalog\Industry\CatalogIndustry;
use Illuminate\Database\Eloquent\Model;

class FactoryIndustry extends Model
{
    protected $table = 'factory_industries';

    protected $fillable = [
        'factory_id',
        'catalog_industry_id',
    ];

    public $timestamps = false;

    /**
     * Get the factory that owns this industry relationship.
     */
    public function factory()
    {
        return $this->belongsTo(Factory::class, 'factory_id');
    }

    /**
     * Get the catalog industry.
     */
    public function catalogIndustry()
    {
        return $this->belongsTo(CatalogIndustry::class, 'catalog_industry_id');
    }
}
