<?php

namespace App\Models\Customer\Designer;

use App\Models\Catalog\Product\CatalogProduct;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorDesignTemplateStoreVariant extends Model
{
    protected $table = 'vendor_design_template_store_variants';

    protected $fillable = [
        'vendor_design_template_store_id',
        'catalog_product_id',
        'sku',
        'markup',
        'markup_type',
        'external_variant_id',
        'is_enabled',
    ];

    protected $casts = [
        'markup' => 'decimal:2',
        'is_enabled' => 'boolean',
    ];

    /**
     * Get the store template association this variant belongs to.
     */
    public function storeTemplate(): BelongsTo
    {
        return $this->belongsTo(VendorDesignTemplateStore::class, 'vendor_design_template_store_id');
    }

    /**
     * Get the catalog product variant.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(CatalogProduct::class, 'catalog_product_id');
    }
}
