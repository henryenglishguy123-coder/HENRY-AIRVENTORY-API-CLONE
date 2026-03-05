<?php

namespace App\Models\Catalog\Product;

use App\Models\Catalog\Attribute\CatalogAttributeOption;
use App\Models\Catalog\DesignTemplate\CatalogDesignTemplateLayer;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CatalogProductLayerImage extends Model
{
    use HasFactory;

    protected $table = 'catalog_product_layer_images';

    protected $fillable = [
        'catalog_product_id',
        'catalog_design_template_layer_id',
        'catalog_attribute_option_id',
        'image_path',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(CatalogProduct::class, 'catalog_product_id');
    }

    public function layer(): BelongsTo
    {
        return $this->belongsTo(CatalogDesignTemplateLayer::class, 'catalog_design_template_layer_id');
    }

    public function attributeOption(): BelongsTo
    {
        return $this->belongsTo(CatalogAttributeOption::class, 'catalog_attribute_option_id');
    }
}
