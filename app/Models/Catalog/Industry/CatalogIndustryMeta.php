<?php

namespace App\Models\Catalog\Industry;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CatalogIndustryMeta extends Model
{
    use HasFactory;

    protected $fillable = ['catalog_industry_id', 'name', 'status'];

    public function industry()
    {
        return $this->belongsTo(CatalogIndustry::class, 'catalog_industry_id');
    }

    protected static function booted(): void
    {
        static::created(function ($meta) {
            self::flushCache($meta);
        });

        static::updated(function ($meta) {
            self::flushCache($meta);
        });

        static::deleted(function ($meta) {
            self::flushCache($meta);
        });
    }

    protected static function flushCache(self $meta): void
    {
        CatalogIndustry::flushIndustryCache();
        if ($meta->catalog_industry_id) {
            cache()->forget('catalog_industries_single_'.$meta->catalog_industry_id);
        }
    }
}
