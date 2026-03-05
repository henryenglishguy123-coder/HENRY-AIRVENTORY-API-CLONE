<?php

namespace App\Models\Customer\Designer;

use App\Models\Catalog\Attribute\CatalogAttributeOption;
use App\Models\Catalog\DesignTemplate\CatalogDesignTemplateLayer;
use App\Models\Catalog\Product\CatalogProduct;
use Illuminate\Database\Eloquent\Model;

class VendorDesignLayerImage extends Model
{
    protected $table = 'vendor_design_layer_images';

    protected $fillable = [
        'template_id',
        'layer_id',
        'product_id',
        'variant_id',
        'color_id',
        'vendor_id',
        'image',
    ];

    /* ======================
     | Relationships
     ====================== */

    public function template()
    {
        return $this->belongsTo(VendorDesignTemplate::class, 'template_id');
    }

    public function layer()
    {
        return $this->belongsTo(CatalogDesignTemplateLayer::class, 'layer_id');
    }

    public function product()
    {
        return $this->belongsTo(CatalogProduct::class, 'product_id');
    }

    public function variant()
    {
        return $this->belongsTo(CatalogProduct::class, 'variant_id');
    }

    public function color()
    {
        return $this->belongsTo(CatalogAttributeOption::class, 'color_id', 'option_id');
    }
}
