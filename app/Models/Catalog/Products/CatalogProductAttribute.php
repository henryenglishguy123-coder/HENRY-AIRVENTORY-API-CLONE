<?php

namespace App\Models\Catalog\Products;

use App\Models\Catalog\Attribute\CatalogAttribute;
use App\Models\Catalog\Attribute\CatalogAttributeOption;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CatalogProductAttribute extends Model
{
    protected $fillable = [
        'catalog_product_id',
        'catalog_attribute_id',
        'attribute_value',
    ];

    public $timestamps = false;

    public function option(): BelongsTo
    {
        return $this->belongsTo(CatalogAttributeOption::class, 'attribute_value', 'option_id');
    }

    public function attribute(): BelongsTo
    {
        return $this->belongsTo(CatalogAttribute::class, 'catalog_attribute_id', 'attribute_id');
    }
}
