<?php

namespace App\Models\Catalog\Category;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class CatalogCategoryMeta extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'catalog_category_id',
        'name',
        'image',
        'description',
        'status',
        'meta_title',
        'meta_description',
    ];

    protected static function booted()
    {
        static::saved(function ($meta) {
            self::clearCategoryCache();
        });

        static::deleted(function ($meta) {
            self::clearCategoryCache();
        });
    }

    /**
     * Clear category cache
     */
    public static function clearCategoryCache()
    {
        Cache::forget('catalog_categories_all');
    }

    public function category()
    {
        return $this->belongsTo(CatalogCategory::class, 'catalog_category_id');
    }
}
