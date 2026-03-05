<?php

namespace App\Models\Customer\Designer;

use App\Models\Customer\Store\VendorConnectedStore;
use App\Models\Customer\Vendor;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorDesignTemplateStore extends Model
{
    protected $table = 'vendor_design_template_stores';

    protected $fillable = [
        'vendor_id',
        'vendor_design_template_id',
        'vendor_connected_store_id',
        'name',
        'sku',
        'description',
        'status',
        'sync_status',
        'external_product_id',
        'sync_error',
        'is_link_only',
        'hang_tag_id',
        'packaging_label_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_link_only' => 'boolean',
    ];

    /**
     * Get the vendor that owns this store association.
     */
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    /**
     * Get the design template associated with this store.
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(VendorDesignTemplate::class, 'vendor_design_template_id');
    }

    /**
     * Get the connected store.
     */
    public function connectedStore(): BelongsTo
    {
        return $this->belongsTo(VendorConnectedStore::class, 'vendor_connected_store_id');
    }

    /**
     * Get the store-specific variants.
     */
    public function variants(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(VendorDesignTemplateStoreVariant::class, 'vendor_design_template_store_id');
    }

    /**
     * Get all store-specific images.
     */
    public function images(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(VendorDesignTemplateStoreImage::class, 'vendor_design_template_store_id');
    }

    /**
     * Get the primary image for this store connection.
     */
    public function primaryImage(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(VendorDesignTemplateStoreImage::class, 'vendor_design_template_store_id')->where('is_primary', true);
    }

    /**
     * Get the sync images for this store connection.
     */
    public function syncImages(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(VendorDesignTemplateStoreImage::class, 'vendor_design_template_store_id')->where('is_primary', false);
    }

    /**
     * Get the branding assigned as a hang tag.
     */
    public function hangTagBranding(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Customer\Branding\VendorDesignBranding::class, 'hang_tag_id');
    }

    /**
     * Get the branding assigned as a packaging label.
     */
    public function packagingLabelBranding(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Customer\Branding\VendorDesignBranding::class, 'packaging_label_id');
    }

    /**
     * Helper to get branding data for order creation.
     */
    protected function storeBranding(): Attribute
    {
        return Attribute::get(fn () => (object) [
            'packaging_label_id' => $this->packaging_label_id,
            'hang_tag_id' => $this->hang_tag_id,
        ]);
    }
}
