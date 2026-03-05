<?php

namespace App\Services\Template;

use App\Models\Customer\Designer\VendorDesignTemplate;
use Illuminate\Support\Facades\Cache;

class TemplateDetailsService
{
    /**
     * Load all necessary details for template display with optimized queries.
     * Splits loading logic to avoid unnecessary relationships.
     */
    public function loadTemplateDetails(VendorDesignTemplate $template): VendorDesignTemplate
    {
        // Load basic template info and relationships
        $template->load([
            'layers.technique',
            'information',
            'designImages.layer',
            'manufacturingFactory',
        ]);

        // Load product with variants and pricing
        if (! $template->relationLoaded('product')) {
            $template->load([
                'product.info',
                'product.printingPrices',
                'product.children' => function ($query) {
                    // children is a BelongsToMany relationship; parent_id is on the pivot table, not the products table
                    $query->select('catalog_products.id', 'catalog_products.sku')
                        ->with([
                            'pricesWithMargin',
                            'attributes' => function ($attrQuery) {
                                $attrQuery->with(['option', 'attribute']);
                            },
                        ]);
                },
            ]);
        }

        return $template;
    }

    /**
     * Load store-specific details and variants with proper eager loading.
     */
    public function loadStoreDetails(
        VendorDesignTemplate $template,
        int $storeId
    ): VendorDesignTemplate {
        // Load store-specific overrides (avoid wasted query checking if store exists)
        $template->load([
            'storeOverrides' => function ($query) use ($storeId) {
                $query
                    ->where('vendor_connected_store_id', $storeId)
                    ->with([
                        'primaryImage:id,vendor_design_template_store_id,image_path,is_primary',
                        'syncImages:id,vendor_design_template_store_id,image_path,is_primary',
                        'variants:id,vendor_design_template_store_id,catalog_product_id,sku,markup,markup_type,is_enabled',
                    ]);
            },
        ]);

        return $template;
    }

    /**
     * Load product variant pricing with caching for performance.
     * Returns cached pricing data keyed by variant ID.
     * Assumes all required relationships loaded by loadTemplateDetails().
     */
    public function getVariantPrices(
        VendorDesignTemplate $template,
        ?int $factoryId = null
    ): array {
        // All required relationships should be loaded - skip loadMissing()

        // Only use cache if we have actual data
        if (! $template->relationLoaded('product') || ! $template->product) {
            return [];
        }

        if (! $template->product->relationLoaded('children') || $template->product->children->isEmpty()) {
            return [];
        }

        $cacheKey = "template_prices:{$template->id}:factory_{$factoryId}";

        // Use cache tags for easy invalidation by template AND factory
        $tags = [
            "template_{$template->id}",
            "product_{$template->product->id}",
        ];
        if ($factoryId) {
            $tags[] = "factory_price_{$factoryId}";
        }

        return Cache::tags($tags)->remember($cacheKey, now()->addHours(6), function () use ($template, $factoryId) {
            // Pre-build printing cost calculation map once (OPTIMIZATION: avoid rebuilding per variant)
            $printingCostByLayerKey = $this->buildPrintingCostMap($template);

            $prices = [];

            foreach ($template->product->children as $variant) {
                $prices[$variant->id] = $this->calculateVariantPrice(
                    $template,
                    $variant,
                    $factoryId,
                    $printingCostByLayerKey
                );
            }

            return $prices;
        });
    }

    /**
     * Build printing cost lookup map once for all variants.
     * Pre-calculates all printing costs to avoid rebuilding for each variant.
     */
    private function buildPrintingCostMap(VendorDesignTemplate $template): array
    {
        $costMap = [];

        if (! $template->relationLoaded('product') || ! $template->product) {
            return $costMap;
        }

        if (! $template->product->relationLoaded('printingPrices')) {
            $template->product->load('printingPrices');
        }

        if (! $template->relationLoaded('layers')) {
            $template->load('layers');
        }

        // Build printing price lookup by layer_key
        $printingPriceMap = $template->product->printingPrices
            ->groupBy(function ($price) {
                return "{$price->layer_id}_{$price->printing_technique_id}";
            });

        // Pre-calculate total cost once
        $totalCost = 0;
        foreach ($template->layers as $layer) {
            $key = "{$layer->catalog_design_template_layer_id}_{$layer->technique_id}";
            $printingPrice = $printingPriceMap->get($key)?->first();

            if ($printingPrice) {
                $totalCost += (float) $printingPrice->price;
            }
        }

        return ['total' => $totalCost];
    }

    /**
     * Calculate single variant price with printing costs.
     * Assumes pricesWithMargin is already loaded to avoid N+1 queries.
     */
    private function calculateVariantPrice(
        VendorDesignTemplate $template,
        $variant,
        ?int $factoryId = null,
        array $costMap = []
    ): ?array {
        // Ensure pricesWithMargin is loaded (should already be from loadTemplateDetails)
        if (! $variant->relationLoaded('pricesWithMargin')) {
            $variant->load('pricesWithMargin');
        }

        if ($variant->pricesWithMargin->isEmpty()) {
            return null;
        }

        // Find price for specific factory or use first
        $priceObj = $factoryId
            ? $variant->pricesWithMargin->firstWhere('factory_id', $factoryId)
            : $variant->pricesWithMargin->first();

        if (! $priceObj) {
            $priceObj = $variant->pricesWithMargin->first();
        }

        if (! $priceObj) {
            return null;
        }

        $factoryPrice = $priceObj->base_sale_price ?? $priceObj->base_regular_price ?? 0;
        $printingCost = $costMap['total'] ?? 0;

        $totalBase = $factoryPrice + $printingCost;
        $markup = $priceObj->getApplicableMarkupPercentage();

        // Use Margin Formula: Selling Price = Cost / (1 - Margin %)
        $marginFraction = ((float) $markup) / 100;

        if ($marginFraction >= 1) {
            $finalPrice = $totalBase;
        } else {
            $finalPrice = $totalBase / (1 - $marginFraction);
        }

        return [
            'raw_price' => $finalPrice,
            'formatted_price' => format_price($finalPrice),
            'factory_price' => $factoryPrice,
            'printing_cost' => $printingCost,
            'markup_percentage' => $markup,
        ];
    }

    /**
     * Group design images by variant and color for efficient resource transformation.
     * OPTIMIZATION: Skip expensive S3 existence checks and generate URL directly.
     */
    public function groupDesignImages(VendorDesignTemplate $template): array
    {
        // designImages should already be loaded by loadTemplateDetails()
        if (! $template->relationLoaded('designImages')) {
            return [];  // Return early if relationship not loaded to avoid N+1
        }

        return $template->designImages
            ->groupBy(function ($img) {
                return $img->variant_id ?? "color_{$img->color_id}";
            })
            ->map(function ($images) {
                return $images->map(function ($img) {
                    // OPTIMIZATION: Use direct S3 URL instead of getImageUrl()
                    // which makes expensive existence checks on S3
                    $imageUrl = $this->generateDirectImageUrl($img->image);

                    return [
                        'layer_id' => $img->layer_id,
                        'layer_name' => $img->layer->layer_name ?? null,
                        'image' => $imageUrl,
                    ];
                })->values();
            })
            ->toArray();
    }

    /**
     * Generate direct S3 URL without expensive existence checks.
     * OPTIMIZATION: Skip S3 storage->exists() calls which are slow API calls.
     * Public method so Resource classes can use it.
     */
    public function generateDirectImageUrl(?string $filePath): string
    {
        if (blank($filePath)) {
            return '';
        }

        // If already a full URL, return as-is
        if (str_starts_with($filePath, 'http')) {
            return $filePath;
        }

        // Generate S3 URL directly without existence check
        // Files in the database are guaranteed to exist or should be removed
        $path = ltrim($filePath, '/');

        return \Illuminate\Support\Facades\Storage::disk('s3')->url($path);
    }

    /**
     * Clear cache only for this specific template when updated.
     * Uses cache tags to avoid clearing the entire application cache.
     */
    public function clearCache(VendorDesignTemplate $template): void
    {
        // Only flush cache for this specific template, not the entire cache
        Cache::tags(["template_{$template->id}"])->flush();
    }
}
