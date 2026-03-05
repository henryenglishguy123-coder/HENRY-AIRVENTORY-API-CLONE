<?php

namespace App\Observers;

use App\Models\Catalog\Product\CatalogProductPriceWithMargin;
use App\Services\Template\TemplateDetailsService;
use Illuminate\Support\Facades\Cache;

class CatalogProductPriceWithMarginObserver
{
    public function __construct(private TemplateDetailsService $templateService) {}

    /**
     * Clear template pricing cache when product prices with margin change.
     * This handles factory-specific price updates.
     */
    public function saved(CatalogProductPriceWithMargin $price): void
    {
        $this->clearRelatedTemplateCache($price);
    }

    public function deleted(CatalogProductPriceWithMargin $price): void
    {
        $this->clearRelatedTemplateCache($price);
    }

    /**
     * Clear cache for templates using this product with this specific factory.
     * Handles: product price changes per factory
     */
    private function clearRelatedTemplateCache(CatalogProductPriceWithMargin $price): void
    {
        if ($price->catalog_product_id && $price->factory_id) {
            // Clear cache for this specific product + factory combination
            Cache::tags([
                "product_price_{$price->catalog_product_id}",
                "factory_price_{$price->factory_id}",
                "product_{$price->catalog_product_id}_factory_{$price->factory_id}",
            ])->flush();
        }
    }
}
