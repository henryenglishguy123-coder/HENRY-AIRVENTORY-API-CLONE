<?php

namespace App\Observers;

use App\Models\Catalog\Product\CatalogProduct;
use App\Support\Designer\ProductDesignerCache;
use Illuminate\Support\Facades\Cache;

class ProductDesignerFlushObserver
{
    public function saved($model): void
    {
        if ($model->wasChanged()) {
            $this->bump($model);
        }
    }

    public function deleted($model): void
    {
        $this->bump($model);
    }

    public function restored($model): void
    {
        $this->bump($model);
    }

    public function forceDeleted($model): void
    {
        $this->bump($model);
    }

    private function bump($model): void
    {
        $productId = null;
        $slug = null;

        if ($model instanceof CatalogProduct) {
            $productId = $model->id;
            $slug = $model->slug;
        } elseif (! empty($model->catalog_product_id)) {
            $productId = $model->catalog_product_id;
        } elseif (! empty($model->product_id)) {
            $productId = $model->product_id;
        } elseif (method_exists($model, 'product')) {
            $product = $model->relationLoaded('product') && $model->product
                ? $model->product
                : $model->product()->select(['id', 'slug'])->first();

            if ($product) {
                $productId = $product->id;
                $slug = $product->slug;
            }
        }

        if (! $productId) {
            return;
        }

        ProductDesignerCache::bumpVersionByProductId($productId);

        $store = Cache::store(config('cache.catalog_store'));

        if (! $slug) {
            $slug = CatalogProduct::whereKey($productId)->value('slug');
        }

        if (! $slug) {
            return;
        }

        $store->increment('product_card_version:user');
        $store->increment('product_card_version:admin');
        $store->increment("product_details_version:{$slug}");
    }
}
