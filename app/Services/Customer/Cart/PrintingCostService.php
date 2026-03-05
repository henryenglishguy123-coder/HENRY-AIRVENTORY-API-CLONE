<?php

namespace App\Services\Customer\Cart;

use App\Models\Catalog\Product\CatalogProduct;
use App\Models\Customer\Designer\VendorDesignTemplate;
use Illuminate\Validation\ValidationException;

class PrintingCostService
{
    public function calculatePrintingCost(
        CatalogProduct $variant,
        VendorDesignTemplate $template,
        int $factoryId
    ): float {
        $parent = $variant->parent;
        if (! $parent) {
            return 0.0;
        }
        $printingPrices = $parent->printingPrices()->get();
        if ($printingPrices->isEmpty()) {
            return 0.0;
        }
        $layerData = $template->layers()
            ->get(['catalog_design_template_layer_id', 'technique_id'])
            ->map(fn ($layer) => [
                'layer_id' => $layer->catalog_design_template_layer_id,
                'printing_technique_id' => $layer->technique_id,
                'factory_id' => $factoryId,
            ]);
        $indexedPrices = $printingPrices->keyBy(fn ($price) => "{$price->layer_id}_{$price->printing_technique_id}_{$price->factory_id}"
        );
        $total = $layerData->sum(function ($data) use ($indexedPrices) {
            $key = "{$data['layer_id']}_{$data['printing_technique_id']}_{$data['factory_id']}";

            return (float) ($indexedPrices[$key]->price ?? 0);
        });
        if ($total <= 0) {
            throw ValidationException::withMessages([
                'product_id' => __('Printing pricing for the selected template is not available.'),
            ]);
        }

        return $total;
    }
}
