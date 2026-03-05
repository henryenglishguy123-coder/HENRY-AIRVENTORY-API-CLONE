<?php

namespace App\Support\Designer;

use App\Models\Catalog\Product\CatalogProduct;
use Illuminate\Support\Facades\Cache;

class ProductDesignerCache
{
    private const PREFIX = 'product_designer_version:';

    private const INITIAL_VERSION = 1;

    public static function bumpVersionByProductId(?int $productId): void
    {
        if (! $productId) {
            return;
        }

        $slug = CatalogProduct::whereKey($productId)->value('slug');
        if (! $slug) {
            return;
        }

        self::bumpBySlug($slug);
    }

    private static function bumpBySlug(string $slug): void
    {
        $store = Cache::store(config('cache.catalog_store'));
        $key = self::PREFIX.$slug;

        $store->put($key, self::INITIAL_VERSION, now()->addDays(7));
        $store->increment($key);
    }
}
