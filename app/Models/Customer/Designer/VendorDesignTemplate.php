<?php

namespace App\Models\Customer\Designer;

use App\Models\Catalog\DesignTemplate\CatalogDesignTemplate;
use App\Models\Customer\Vendor;
use App\Models\Sales\Order\Item\SalesOrderItem;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class VendorDesignTemplate extends Model
{
    use HasFactory;

    protected $table = 'vendor_design_templates';

    protected $fillable = [
        'vendor_id',
        'catalog_design_template_id',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    // Vendor owning this design template
    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }

    // Base catalog design template
    public function catalogDesignTemplate()
    {
        return $this->belongsTo(
            CatalogDesignTemplate::class,
            'catalog_design_template_id'
        );
    }

    // Layers inside this vendor design
    public function layers()
    {
        return $this->hasMany(
            VendorDesignLayer::class,
            'vendor_design_template_id'
        );
    }

    public function product()
    {
        return $this->hasOneThrough(
            \App\Models\Catalog\Product\CatalogProduct::class,
            \App\Models\Customer\Designer\VendorDesignTemplateCatalogProduct::class,
            'vendor_design_template_id', // FK on pivot table
            'id',                         // FK on catalog_products
            'id',                         // local key on vendor_design_templates
            'catalog_product_id'          // local key on pivot table
        );
    }

    public function manufacturingFactory()
    {
        return $this->hasOneThrough(
            \App\Models\Factory\Factory::class,
            \App\Models\Customer\Designer\VendorDesignTemplateCatalogProduct::class,
            'vendor_design_template_id',
            'id',
            'id',
            'factory_id'
        );
    }

    public function designImages(): HasMany
    {
        return $this->hasMany(
            VendorDesignLayerImage::class,
            'template_id',
            'id'

        );
    }

    public function information()
    {
        return $this->hasOne(VendorDesignTemplateInformation::class, 'vendor_design_template_id');
    }

    public function storeOverrides(): HasMany
    {
        return $this->hasMany(VendorDesignTemplateStore::class, 'vendor_design_template_id');
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(
            SalesOrderItem::class,
            'template_id',
            'id'
        );
    }

    /**
     * Load basic template details without heavy relationships.
     * Use TemplateDetailsService for full loading.
     */
    public function loadBasicDetails()
    {
        return $this->load([
            'information',
            'layers.technique',
            'manufacturingFactory',
        ]);
    }

    /**
     * Load product and its variants for pricing calculations.
     */
    public function loadProductDetails()
    {
        return $this->load([
            'product.info',
            'product.printingPrices',
        ]);
    }

    /**
     * Load design images with their layers.
     */
    public function loadDesignImages()
    {
        return $this->load('designImages.layer');
    }

    /**
     * Optimized method to load all standard details for the template.
     * Delegates to TemplateDetailsService for better caching and performance.
     */
    public function loadDetails()
    {
        $service = app(\App\Services\Template\TemplateDetailsService::class);

        return $service->loadTemplateDetails($this);
    }

    /**
     * Load store-specific overrides for a given store ID.
     */
    public function loadStoreSpecificDetails($storeId)
    {
        $service = app(\App\Services\Template\TemplateDetailsService::class);

        return $service->loadStoreDetails($this, $storeId);
    }

    /**
     * Get branding from the first store override (eager-loadable).
     */
    public function storeBranding(): HasOne
    {
        return $this->hasOne(VendorDesignTemplateStore::class, 'vendor_design_template_id')->oldestOfMany();
    }
}
