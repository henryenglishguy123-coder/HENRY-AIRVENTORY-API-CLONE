<?php

namespace App\Models\Customer\Branding;

use App\Models\Customer\Vendor;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorDesignBranding extends Model
{
    use HasFactory;

    protected $table = 'vendor_design_branding';

    protected $fillable = [
        'vendor_id',
        'image',
        'image_back',
        'width',
        'height',
        'width_back',
        'height_back',
        'name',
        'type',
    ];

    protected $casts = [
        'width' => 'integer',
        'height' => 'integer',
        'width_back' => 'integer',
        'height_back' => 'integer',
    ];

    /**
     * Relationship: Branding belongs to a Vendor
     */
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function getImageUrlAttribute(): ?string
    {
        return getImageUrl($this->image);
    }
}
