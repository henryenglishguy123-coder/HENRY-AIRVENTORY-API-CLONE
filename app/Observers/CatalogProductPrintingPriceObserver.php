<?php

namespace App\Observers;

use App\Models\Catalog\CatalogProductPrintingPrice;
use App\Services\Template\TemplateDetailsService;
use Illuminate\Support\Facades\Cache;

class CatalogProductPrintingPriceObserver
{
    public function __construct(private TemplateDetailsService $templateService) {}

    /**
     * Clear template pricing cache when printing prices change.
     */
    public function saved(CatalogProductPrintingPrice $price): void
    {
        $this->clearRelatedTemplateCache($price);
    }

    public function deleted(CatalogProductPrintingPrice $price): void
    {
        $this->clearRelatedTemplateCache($price);
    }

    /**
     * Find and clear cache for all templates using this product.
     */
    private function clearRelatedTemplateCache(CatalogProductPrintingPrice $price): void
    {
        // Clear cache for all templates using this product
        // Uses wildcard tag matching on product ID
        if ($price->catalog_product_id) {
            Cache::tags(["printing_price_{$price->catalog_product_id}"])->flush();
        }
    }
}
