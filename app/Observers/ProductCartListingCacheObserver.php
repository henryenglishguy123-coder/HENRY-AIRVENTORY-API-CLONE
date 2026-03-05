<?php

namespace App\Observers;

use App\Models\Catalog\Product\CatalogProduct;
use Illuminate\Support\Facades\Cache;

class ProductCartListingCacheObserver
{
    /**
     * Clear product-related caches when any product data changes.
     * Handles: product details, listing, cart, factory pricing updates
     */
    public function saved($model): void
    {
        if ($this->shouldClearCache($model)) {
            $this->clearProductCache($model);
        }
    }

    public function deleted($model): void
    {
        $this->clearProductCache($model);
    }

    public function restored($model): void
    {
        $this->clearProductCache($model);
    }

    public function forceDeleted($model): void
    {
        $this->clearProductCache($model);
    }

    /**
     * Determine if cache should be cleared for this model.
     */
    private function shouldClearCache($model): bool
    {
        // Only clear cache if data was actually changed
        return $model->wasChanged();
    }

    /**
     * Clear all relevant caches for a product.
     */
    private function clearProductCache($model): void
    {
        $productId = $this->getProductId($model);

        if (! $productId) {
            return;
        }

        // Clear all tags related to this product
        $tags = [
            "product_{$productId}",                    // Product details
            "product_price_{$productId}",              // Pricing
            'product_listing',                         // Product listing/cart
            "product_factory_prices_{$productId}",     // Factory-specific prices
            "product_images_{$productId}",             // Product images
            "product_design_{$productId}",             // Design/template data
            'cart_items',                              // Shopping cart
        ];

        Cache::tags($tags)->flush();
    }

    /**
     * Extract product ID from any product-related model.
     */
    private function getProductId($model): ?int
    {
        if ($model instanceof CatalogProduct) {
            return $model->id;
        }

        if (! empty($model->catalog_product_id)) {
            return $model->catalog_product_id;
        }

        if (! empty($model->product_id)) {
            return $model->product_id;
        }

        // Try to get the product via relationship
        if (method_exists($model, 'product')) {
            $product = $model->relationLoaded('product') && $model->product
                ? $model->product
                : $model->product()->select(['id'])->first();

            return $product?->id;
        }

        return null;
    }
}
