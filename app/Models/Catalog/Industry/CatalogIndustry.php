<?php

namespace App\Models\Catalog\Industry;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class CatalogIndustry extends Model
{
    use HasFactory;

    protected $fillable = [
        'slug',
    ];

    /**
     * Get the industry metadata
     */
    public function meta()
    {
        return $this->hasOne(CatalogIndustryMeta::class, 'catalog_industry_id');
    }

    public function category()
    {
        return $this->hasMany(\App\Models\Catalog\Category\CatalogCategory::class, 'catalog_industry_id');
    }

    protected static function booted(): void
    {
        static::created(fn () => self::flushIndustryCache());
        static::updated(function ($industry) {
            self::flushIndustryCache();
            Cache::forget('catalog_industries_single_'.$industry->id);
        });
        static::deleted(function ($industry) {
            self::flushIndustryCache();
            Cache::forget('catalog_industries_single_'.$industry->id);
        });
    }

    public static function flushIndustryCache(): void
    {
        Cache::forget('catalog_industries_all');
        Cache::forget('catalog_industries_all_status_1');
        Cache::forget('catalog_industries_all_status_0');
    }
}
