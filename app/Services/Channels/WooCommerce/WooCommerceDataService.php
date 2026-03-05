<?php

declare(strict_types=1);

namespace App\Services\Channels\WooCommerce;

use App\Models\Customer\Designer\VendorDesignTemplateStore;
use App\Models\Customer\Designer\VendorDesignTemplateStoreVariant;
use App\Services\Currency\CurrencyConversionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class WooCommerceDataService
{
    public function __construct(
        protected ?CurrencyConversionService $currencyService = null
    ) {
        if ($this->currencyService === null) {
            $this->currencyService = app(CurrencyConversionService::class);
        }
    }

    public function ensureProductRelationships(VendorDesignTemplateStore $storeOverride): void
    {
        $storeOverride->load([
            'connectedStore',
            'template.product',
            'template.layers',
            'template.product.printingPrices',
            'template.manufacturingFactory.hangTag',
            'template.manufacturingFactory.packagingLabel',
            'hangTagBranding',
            'packagingLabelBranding',
            'template.designImages',
            'primaryImage',
            'syncImages',
        ]);

        // Lazily eager-load relationships on the product children to avoid an overly large single query.
        $template = $storeOverride->template;
        if ($template && $template->product && $template->product->relationLoaded('children')) {
            $template->product->children->load([
                'attributes.option',
                'attributes.attribute.description',
            ]);
        }

        // Load variant-related product data efficiently
        $storeOverride->load([
            'variants.product.pricesWithMargin',
            'variants.product.attributes.attribute',
            'variants.product.attributes.option',
            'template.designImages',
        ]);
    }

    public function batchUpdateVariantExternalIds(VendorDesignTemplateStore $storeOverride, array $wooVariations): void
    {
        $variantsBySku = $storeOverride->variants->keyBy('sku');
        $variantsById = $storeOverride->variants->keyBy('id');

        $updates = [];

        foreach ($wooVariations as $wooVar) {
            // Skip if error or no ID
            if (! isset($wooVar['id']) || isset($wooVar['error'])) {
                continue;
            }

            // 1. Try match by _vendor_variant_id metadata
            $vendorVariantId = null;
            if (isset($wooVar['meta_data']) && is_array($wooVar['meta_data'])) {
                foreach ($wooVar['meta_data'] as $meta) {
                    if (isset($meta['key'], $meta['value']) && $meta['key'] === '_vendor_variant_id') {
                        $vendorVariantId = $meta['value'];
                        break;
                    }
                }
            }

            $matchedId = null;
            if ($vendorVariantId && $variantsById->has((int) $vendorVariantId)) {
                $matchedId = (int) $vendorVariantId;
            } elseif (isset($wooVar['sku']) && $variantsBySku->has($wooVar['sku'])) {
                $matchedId = $variantsBySku->get($wooVar['sku'])->id;
            }

            if ($matchedId) {
                $updates[$matchedId] = $wooVar['id'];
            } else {
                if (isset($wooVar['sku'])) {
                    Log::warning('Variant not found for update during batch sync', ['sku' => $wooVar['sku'] ?? 'N/A', 'woo_id' => $wooVar['id']]);
                }
            }
        }

        if (empty($updates)) {
            return;
        }

        $ids = array_keys($updates);
        $cases = [];
        $params = [];

        foreach ($updates as $id => $externalId) {
            $cases[] = 'WHEN ? THEN ?';
            $params[] = $id;
            $params[] = $externalId;
        }

        // Add IDs again for the WHERE IN clause
        foreach ($ids as $id) {
            $params[] = $id;
        }

        $table = $storeOverride->variants()->getModel()->getTable();
        $casesString = implode(' ', $cases);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        DB::update("UPDATE {$table} SET external_variant_id = CASE id {$casesString} END WHERE id IN ({$placeholders})", $params);
    }

    public function reconcileVariations(VendorDesignTemplateStore $storeOverride, \Illuminate\Support\Collection $wooVariations): array
    {
        $variantsBySku = $storeOverride->variants->whereNotNull('sku')->keyBy('sku');
        $variantsById = $storeOverride->variants->keyBy('id');
        $matchedWooIds = [];

        foreach ($wooVariations as $wooVar) {
            $wooId = $wooVar['id'];
            $matchedLocalId = null;

            // 1. Try match by _vendor_variant_id metadata
            if (isset($wooVar['meta_data']) && is_array($wooVar['meta_data'])) {
                foreach ($wooVar['meta_data'] as $meta) {
                    if (isset($meta['key'], $meta['value']) && $meta['key'] === '_vendor_variant_id') {
                        $vendorVariantId = (int) $meta['value'];
                        if ($variantsById->has($vendorVariantId)) {
                            $matchedLocalId = $vendorVariantId;
                        }
                        break;
                    }
                }
            }

            // 2. Try match by SKU if not found
            if (! $matchedLocalId && isset($wooVar['sku']) && $variantsBySku->has($wooVar['sku'])) {
                $matchedLocalId = $variantsBySku->get($wooVar['sku'])->id;
            }

            if ($matchedLocalId) {
                $matchedWooIds[] = $wooId;
                // Update local variant if ID is missing or different
                $variant = $variantsById->get($matchedLocalId);
                if ((int) $variant->external_variant_id !== (int) $wooId) {
                    $variant->external_variant_id = $wooId;
                    $variant->save();
                }
            }
        }

        // Return IDs of WooCommerce variations that were NOT matched (orphans)
        // These are variations on WooCommerce that we don't have locally anymore
        return $wooVariations->pluck('id')->diff($matchedWooIds)->values()->all();
    }

    public function prepareProductData(VendorDesignTemplateStore $storeOverride): array
    {
        $vendorTemplate = $storeOverride->template;
        $catalogProduct = $vendorTemplate->product;
        // Treat "variable" as more than one variant
        $hasVariants = $storeOverride->variants->count() > 0;

        $images = $this->prepareImages($storeOverride);

        // Only generate and persist a SKU if one does not already exist
        $productSku = $storeOverride->sku;
        if (empty($productSku)) {
            $baseSku = $this->generateProductSku($storeOverride);
            // Append a UUID suffix to avoid collisions when multiple syncs occur within the same second
            $productSku = $baseSku.'-'.Str::uuid()->toString();
            // Update the stored SKU to match the one being synced
            $storeOverride->sku = $productSku;
            $storeOverride->save();
        }

        $data = [
            'name' => $storeOverride->name,
            'type' => $hasVariants ? 'variable' : 'simple',
            'status' => 'publish',
            'description' => $storeOverride->description ?? '',
            'sku' => $productSku,
            'weight' => (string) $catalogProduct->weight,
            'images' => $images,
            'manage_stock' => false,
        ];

        if ($hasVariants) {
            $data['attributes'] = $this->prepareAttributes($storeOverride);
        } else {
            $variant = $storeOverride->variants->first();
            if ($variant) {
                $price = $this->calculatePrice($variant, $storeOverride);
                if ($price !== null) {
                    $data['regular_price'] = (string) $price;
                }
            }
        }

        return $data;
    }

    public function getVariationBatches(VendorDesignTemplateStore $storeOverride, ?int $batchSize = null): \Generator
    {
        $batchSize = $batchSize ?? config('woocommerce.sync.variation_batch_size', 50);
        $payload = $this->prepareVariationsData($storeOverride);
        $allCreate = $payload['create'];
        $allUpdate = $payload['update'];

        while (! empty($allCreate) || ! empty($allUpdate)) {
            $batchCreate = [];
            $batchUpdate = [];
            $count = 0;

            // Fill batch with Creates first
            while (! empty($allCreate) && $count < $batchSize) {
                $batchCreate[] = array_shift($allCreate);
                $count++;
            }

            // Fill remaining space with Updates
            while (! empty($allUpdate) && $count < $batchSize) {
                $batchUpdate[] = array_shift($allUpdate);
                $count++;
            }

            yield [
                'create' => $batchCreate,
                'update' => $batchUpdate,
            ];
        }
    }

    public function prepareVariationsData(VendorDesignTemplateStore $storeOverride): array
    {
        $create = [];
        $update = [];

        Log::info('Preparing variations data', [
            'store_override_id' => $storeOverride->id,
            'total_variants' => $storeOverride->variants->count(),
        ]);

        foreach ($storeOverride->variants as $variant) {
            try {
                $variationData = $this->prepareSingleVariationData($variant, $storeOverride);
                if ($variant->external_variant_id) {
                    $variationData['id'] = (int) $variant->external_variant_id;
                    $update[] = $variationData;
                } else {
                    $create[] = $variationData;
                }
            } catch (\Throwable $e) {
                Log::error('Error preparing single variation data', [
                    'variant_id' => $variant->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return [
            'create' => $create,
            'update' => $update,
        ];
    }

    private function prepareSingleVariationData(VendorDesignTemplateStoreVariant $variant, VendorDesignTemplateStore $storeOverride): array
    {
        $price = $this->calculatePrice($variant, $storeOverride);
        $attributes = [];

        $product = $variant->product;
        // Ensure relationships are loaded if not already?
        // Assuming they are loaded by the caller for performance

        if ($product && $product->attributes) {
            foreach ($product->attributes as $prodAttr) {
                if (! $prodAttr->attribute || ! $prodAttr->option) {
                    continue;
                }
                $attrName = $prodAttr->attribute->description?->name ?? $prodAttr->attribute->attribute_code ?? '';
                $optionName = $prodAttr->option->key ?? '';

                if ($attrName && $optionName) {
                    $attributes[] = [
                        'name' => $attrName,
                        'option' => $optionName,
                    ];
                }
            }
        }

        $image = null;
        if ($storeOverride->template && $storeOverride->template->designImages) {
            $designImages = $storeOverride->template->designImages;

            // 1. Try match by variant_id
            $matchingImages = $designImages->where('variant_id', $product->id);

            // 2. If not found, match by color_id
            if ($matchingImages->isEmpty() && $product->attributes) {
                $colorAttr = $product->attributes->first(function ($attr) {
                    return optional($attr->attribute)->attribute_code === 'color';
                });

                if ($colorAttr && $colorAttr->option) {
                    $matchingImages = $designImages->where('color_id', $colorAttr->option->option_id);
                }
            }

            // Get the first image from the matching collection
            $img = $matchingImages->first();

            if ($img && $img->image) {
                $image = ['src' => Storage::url($img->image)];
            }
        }

        $varSku = $variant->sku ?: $this->generateVariantSku($variant, $storeOverride);

        // Only generate and persist a SKU if one does not already exist
        if (empty($variant->sku)) {
            $variant->sku = $varSku;
            $variant->save();
        }

        $data = [
            'regular_price' => (string) $price,
            'attributes' => $attributes,
            'sku' => $varSku,
            'weight' => isset($product) && isset($product->weight) ? (string) $product->weight : '',
            'manage_stock' => false,
            'meta_data' => [
                [
                    'key' => '_vendor_variant_id',
                    'value' => (string) $variant->id,
                ],
            ],
        ];

        if ($image) {
            $data['image'] = $image;
        }

        return $data;
    }

    private function prepareImages(VendorDesignTemplateStore $storeOverride): array
    {
        $images = [];

        if ($storeOverride->primaryImage?->image_path) {
            $images[] = [
                'src' => Storage::url($storeOverride->primaryImage->image_path),
            ];
        }

        foreach ($storeOverride->syncImages as $img) {
            if ($img->image_path) {
                $images[] = [
                    'src' => Storage::url($img->image_path),
                ];
            }
        }

        return $images;
    }

    private function prepareAttributes(VendorDesignTemplateStore $storeOverride): array
    {
        $attributeMap = [];

        foreach ($storeOverride->variants as $variant) {
            if (! $variant->is_enabled) {
                continue;
            }

            $product = $variant->product;
            if (! $product || ! $product->attributes) {
                continue;
            }

            foreach ($product->attributes as $prodAttr) {
                if (! $prodAttr->attribute || ! $prodAttr->option) {
                    continue;
                }

                $attrName = $prodAttr->attribute->description->name ?? $prodAttr->attribute->attribute_code ?? '';
                $optionName = $prodAttr->option->key ?? '';

                if (! $attrName || ! $optionName) {
                    continue;
                }

                if (! isset($attributeMap[$attrName])) {
                    $attributeMap[$attrName] = [
                        'name' => $attrName,
                        'options' => [],
                        'visible' => true,
                        'variation' => true,
                    ];
                }

                if (! in_array($optionName, $attributeMap[$attrName]['options'])) {
                    $attributeMap[$attrName]['options'][] = $optionName;
                }
            }
        }

        return array_values($attributeMap);
    }

    public function calculatePrice(VendorDesignTemplateStoreVariant $variant, ?VendorDesignTemplateStore $storeOverride = null): ?float
    {
        if (! $variant || ! $variant->product) {
            return null;
        }

        $template = $storeOverride ? $storeOverride->template : null;

        // 1. Get Base Price (Factory Price)
        $priceObj = null;
        if ($template && $template->manufacturingFactory) {
            $priceObj = $variant->product->pricesWithMargin->firstWhere('factory_id', $template->manufacturingFactory->id);
        }
        if (! $priceObj) {
            $priceObj = $variant->product->pricesWithMargin->first();
        }

        $basePrice = $priceObj ? ($priceObj->base_sale_price ?? $priceObj->base_regular_price ?? 0) : 0;

        // 2. Add Printing Cost
        $printingCost = 0;

        if ($template && $template->layers && $template->product && $template->product->printingPrices) {
            foreach ($template->layers as $layer) {
                $pp = $template->product->printingPrices->first(function ($p) use ($layer) {
                    return $p->layer_id == $layer->catalog_design_template_layer_id
                        && $p->printing_technique_id == $layer->technique_id;
                });
                if ($pp) {
                    $printingCost += (float) $pp->price;
                }
            }
        }

        // 3. Add Hang Tag & Packaging Label Cost
        $packagingAddon = $storeOverride ? $this->calculatePackagingAddon($storeOverride) : 0.0;

        $totalBase = $basePrice + $printingCost + $packagingAddon;

        // 3. Apply Admin Markup
        $marginPercentage = 0;
        if ($priceObj instanceof \App\Models\Catalog\Product\CatalogProductPriceWithMargin) {
            $marginPercentage = $priceObj->getApplicableMarkupPercentage();
        } elseif ($priceObj) {
            $specificMarkup = $priceObj->specific_markup ?? null;
            $marginPercentage = (! is_null($specificMarkup) && is_numeric($specificMarkup))
                ? (float) $specificMarkup
                : \App\Models\Catalog\Product\CatalogProductPriceWithMargin::getGlobalMarkup();
        }

        $marginDecimal = max(0, (float) $marginPercentage) / 100;
        if ($marginDecimal >= 1) {
            $vendorCost = $totalBase; // avoid division by zero
        } else {
            $vendorCost = $totalBase / (1 - $marginDecimal);
        }

        // 4. Apply Vendor (Store) Markup
        $markupType = $variant->markup_type ?? 'fixed';
        $markupValue = is_numeric($variant->markup ?? null) ? (float) $variant->markup : 0.0;

        if ($markupType === 'percentage' && $markupValue > 0) {
            // Margin Formula: Price = Cost / (1 - (Margin% / 100))
            // Validation: 125 - 100 = 25. 25 is 20% of 125.
            if ($markupValue >= 100) {
                // Prevent division by zero or negative price if margin is 100% or more (impossible mathematically for margin, possible for markup)
                // Assuming user means Markup if > 99? Or just cap it?
                // Standard margin cannot be >= 100%.
                // Let's fallback to Markup behavior if invalid margin, or clamp.
                // For safety, let's treat it as Markup if >= 90 to avoid explosion.
                $finalPrice = $vendorCost + ($vendorCost * ($markupValue / 100));
            } else {
                $finalPrice = $vendorCost / (1 - ($markupValue / 100));
            }
        } else {
            $finalPrice = $vendorCost + $markupValue;
        }

        // 5. Convert Currency if needed
        if ($storeOverride && $storeOverride->connectedStore && $storeOverride->connectedStore->currency) {
            $storeCurrency = $storeOverride->connectedStore->currency;
            $finalPrice = $this->currencyService->convert($finalPrice, $storeCurrency);
        }

        return $finalPrice;
    }

    private function calculatePackagingAddon(VendorDesignTemplateStore $storeOverride): float
    {
        $addon = 0.0;
        $factory = $storeOverride->template?->manufacturingFactory;

        // 1. Handle Hang Tag
        if ($factory && $storeOverride->hang_tag_id) {
            $hangTag = $factory->hangTag;
            $branding = $storeOverride->hangTagBranding;

            if ($hangTag && $hangTag->is_active && $branding) {
                if ($branding->image) {
                    $addon += (float) ($hangTag->front_price ?? 0);
                }
                if ($branding->image_back) {
                    $addon += (float) ($hangTag->back_price ?? 0);
                }
            }
        }

        // 2. Handle Packaging Label
        if ($factory && $storeOverride->packaging_label_id) {
            $packagingLabel = $factory->packagingLabel;
            $branding = $storeOverride->packagingLabelBranding;

            if ($packagingLabel && $packagingLabel->is_active && $branding) {
                if ($branding->image) {
                    $addon += (float) ($packagingLabel->front_price ?? 0);
                }
                if ($branding->image_back) {
                    $addon += (float) ($packagingLabel->back_price ?? 0);
                }
            }
        }

        return $addon;
    }

    private function generateProductSku(VendorDesignTemplateStore $storeOverride): ?string
    {
        // Generate numeric-only SKU based on time and ID
        // Format: YmdHis + StoreOverrideID (e.g., 202402071230451)
        return date('YmdHis').$storeOverride->id;
    }

    private function generateVariantSku(VendorDesignTemplateStoreVariant $variant, VendorDesignTemplateStore $storeOverride): ?string
    {
        // Variant SKU should be based on the Product SKU to ensure consistency
        $baseSku = $storeOverride->sku;

        // Fallback if Product SKU is missing (should not happen in normal flow)
        if (empty($baseSku)) {
            $baseSku = date('YmdHis').$storeOverride->id;
        }

        // Append Variant ID to ensure uniqueness
        return $baseSku.'-'.$variant->id;
    }
}
