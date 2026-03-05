<?php

namespace App\Models\Catalog\DesignTemplate;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class CatalogDesignTemplateLayer extends Model
{
    use HasFactory;

    protected $table = 'catalog_design_template_layers';

    protected $fillable = [
        'catalog_design_template_id',
        'layer_name',
        'coordinates',
        'image',
        'is_neck_layer',
    ];

    protected $casts = [
        'coordinates' => 'array',
    ];

    public function design()
    {
        return $this->belongsTo(CatalogDesignTemplate::class, 'catalog_design_template_id');
    }

    public function products(): HasManyThrough
    {
        return $this->hasManyThrough(
            \App\Models\Catalog\Product\CatalogProduct::class,
            \App\Models\Catalog\Product\CatalogProductLayerImage::class,
            'catalog_design_template_layer_id', // FK on product_layer_images
            'id',                               // FK on products
            'id',                               // PK on layers
            'catalog_product_id'               // FK on product_layer_images → products
        )->distinct();
    }
}
