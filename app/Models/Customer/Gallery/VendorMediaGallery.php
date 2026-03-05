<?php

namespace App\Models\Customer\Gallery;

use App\Models\Customer\Vendor;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VendorMediaGallery extends Model
{
    use HasFactory;

    protected $table = 'vendor_media_gallery';

    protected $fillable = [
        'vendor_id',
        'image_path',
        'original_name',
        'extension',
        'last_used_at',
    ];

    protected $casts = [
        'vendor_id' => 'integer',
        'last_used_at' => 'datetime',
    ];

    /**
     * Vendor who owns this media
     */
    public function vendor()
    {
        return $this->belongsTo(Vendor::class, 'vendor_id');
    }

    /**
     * Get full public URL of media
     */
    public function getUrlAttribute(): string
    {
        return getImageUrl($this->image_path);
    }
}
