<?php

namespace App\Models\Customer\Designer;

use App\Models\Catalog\Product\CatalogProduct;
use Illuminate\Database\Eloquent\Model;

class VendorDesignTemplateCatalogProduct extends Model
{
    protected $table = 'vendor_design_template_to_catalog_product';

    protected $fillable = [
        'vendor_id',
        'vendor_design_template_id',
        'catalog_product_id',
        'factory_id',
    ];

    public function product()
    {
        return $this->belongsTo(
            CatalogProduct::class,
            'catalog_product_id'
        );
    }
}
