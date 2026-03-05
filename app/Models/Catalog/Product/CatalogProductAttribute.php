<?php

namespace App\Models\Catalog\Product;

use App\Models\Catalog\Attribute\CatalogAttribute;
use App\Models\Catalog\Attribute\CatalogAttributeOption;
use Illuminate\Database\Eloquent\Model;

class CatalogProductAttribute extends Model
{
    protected $fillable = [
        'catalog_product_id',
        'catalog_attribute_id',
        'attribute_value',
    ];

    public $timestamps = false;

    public function option()
    {
        return $this->belongsTo(CatalogAttributeOption::class, 'attribute_value', 'option_id');
    }

    public function attribute()
    {
        return $this->belongsTo(CatalogAttribute::class, 'catalog_attribute_id', 'attribute_id');
    }
}
