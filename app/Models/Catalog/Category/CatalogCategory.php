<?php

namespace App\Models\Catalog\Category;

use App\Models\Catalog\Industry\CatalogIndustry;
use App\Models\Catalog\Product\CatalogProduct;
use App\Models\Catalog\Products\CatalogProductCategory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class CatalogCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'slug',
        'parent_id',
        'catalog_industry_id',
    ];

    protected static function booted()
    {
        static::saved(function ($category) {
            self::clearCategoryCache($category);
        });

        static::deleted(function ($category) {
            self::clearCategoryCache($category);
        });
    }

    public static function clearCategoryCache(?CatalogCategory $category = null): void
    {
        Cache::forget('catalog_categories_all_root');
        if ($category?->slug) {
            Cache::forget('catalog_categories_all_slug_'.$category->slug);
            Cache::forget(sprintf('catalog.category.details.%s', $category->slug));
        }
        if ($category) {
            $children = $category->children()->pluck('slug');
            foreach ($children as $slug) {
                Cache::forget('catalog_categories_all_slug_'.$slug);
            }
        }
    }

    public function getPathAttribute()
    {
        $ancestors = collect();
        $category = $this;
        while ($category->parent) {
            $ancestors->prepend($category->parent->meta->name ?? $category->parent->name ?? '');
            $category = $category->parent;
        }
        $ancestors->push($this->meta->name ?? $this->name ?? '');

        return $ancestors->filter()->implode(' > ');
    }

    public function getSlugPathAttribute(): string
    {
        $ancestors = collect();
        $category = $this;
        while ($category->parent) {
            $ancestors->prepend($category->parent->slug);
            $category = $category->parent;
        }
        $ancestors->push($this->slug);

        return $ancestors->filter()->implode('/');
    }

    public function meta()
    {
        return $this->hasOne(CatalogCategoryMeta::class, 'catalog_category_id');
    }

    public function children()
    {
        return $this->hasMany(CatalogCategory::class, 'parent_id');
    }

    public function parent()
    {
        return $this->belongsTo(CatalogCategory::class, 'parent_id');
    }

    public function recursiveParent()
    {
        return $this->parent()->with('recursiveParent');
    }

    public function industry()
    {
        return $this->belongsTo(CatalogIndustry::class, 'catalog_industry_id');
    }

    public function products()
    {
        return $this->belongsToMany(
            CatalogProduct::class,
            CatalogProductCategory::class,
            'catalog_category_id',
            'catalog_product_id'
        );
    }
}
