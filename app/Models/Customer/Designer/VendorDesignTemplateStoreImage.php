<?php

namespace App\Models\Customer\Designer;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorDesignTemplateStoreImage extends Model
{
    protected $table = 'vendor_design_template_store_images';

    protected $fillable = [
        'vendor_design_template_store_id',
        'image_path',
        'is_primary',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
    ];

    /**
     * Get the store template association this image belongs to.
     */
    public function storeTemplate(): BelongsTo
    {
        return $this->belongsTo(VendorDesignTemplateStore::class, 'vendor_design_template_store_id');
    }

    public function getImageUrlAttribute()
    {
        if (empty($this->image_path)) {
            return null;
        }

        if (filter_var($this->image_path, FILTER_VALIDATE_URL)) {
            return $this->image_path;
        }

        return getImageUrl($this->image_path);
    }
}
