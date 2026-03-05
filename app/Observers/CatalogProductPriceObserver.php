<?php

namespace App\Observers;

use App\Models\Catalog\Product\CatalogProductPrice;
use App\Services\Template\TemplateDetailsService;
use Illuminate\Support\Facades\Cache;

class CatalogProductPriceObserver
{
    public function __construct(private TemplateDetailsService $templateService) {}

    /**
     * Clear template pricing cache when base product prices change.
     * This handles general product price updates.
     */
    public function saved(CatalogProductPrice $price): void
    {
        $this->clearRelatedTemplateCache($price);
    }

    public function deleted(CatalogProductPrice $price): void
    {
        $this->clearRelatedTemplateCache($price);
    }

    /**
     * Clear cache for templates using this product.
     * Handles: base product price changes
     */
    private function clearRelatedTemplateCache(CatalogProductPrice $price): void
    {
        if ($price->catalog_product_id) {
            // Clear cache for this product and all its factory variants
            Cache::tags([
                "product_price_{$price->catalog_product_id}",
                "product_{$price->catalog_product_id}",
            ])->flush();
        }
    }
}
