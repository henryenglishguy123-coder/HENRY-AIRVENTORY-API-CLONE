<?php

declare(strict_types=1);

namespace App\Services\Channels\Shopify;

use App\Models\Customer\Designer\VendorDesignTemplateStore;
use App\Models\Customer\Designer\VendorDesignTemplateStoreVariant;
use App\Services\Currency\CurrencyConversionService;
use App\Services\StoreConfigService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ShopifyDataService
{
    public function __construct(
        protected ?CurrencyConversionService $currencyService = null,
        protected ?StoreConfigService $storeConfigService = null
    ) {
        if ($this->currencyService === null) {
            $this->currencyService = app(CurrencyConversionService::class);
        }
        if ($this->storeConfigService === null) {
            $this->storeConfigService = app(StoreConfigService::class);
        }
    }

    public function ensureProductRelationships(VendorDesignTemplateStore $storeOverride): void
    {
        $storeOverride->load([
            'connectedStore',
            'template.product.children', // Load children here
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

        $template = $storeOverride->template;
        if ($template && $template->product && $template->product->relationLoaded('children')) {
            $template->product->children->load([
                'attributes.option',
                'attributes.attribute.description',
            ]);
        }

        $storeOverride->load([
            'variants.product.pricesWithMargin',
            'variants.product.attributes.attribute',
            'variants.product.attributes.option',
            'template.designImages',
        ]);
    }

    public function prepareProductData(VendorDesignTemplateStore $storeOverride): array
    {
        $hasVariants = $storeOverride->variants->isNotEmpty();
        $images = $this->prepareImages($storeOverride);

        $productSku = $storeOverride->sku;
        if (empty($productSku)) {
            $baseSku = $this->generateProductSku($storeOverride);
            // Append a UUID suffix to avoid collisions when multiple syncs occur within the same second
            $productSku = $baseSku.'-'.Str::uuid()->toString();
            $storeOverride->sku = $productSku;
            $storeOverride->save();
        }

        $data = [
            'product' => [
                'title' => $storeOverride->name,
                'body_html' => $storeOverride->description ?? '',
                'vendor' => $storeOverride->connectedStore?->store_identifier ?? 'Airventory',
                'product_type' => 'Apparel', // Could be dynamic
                'status' => 'active',
                'published_scope' => 'global',
                'images' => $images,
            ],
        ];

        if ($hasVariants) {
            $data['product']['options'] = $this->prepareOptions($storeOverride);
        } else {
            // For single-variant products, set the SKU on the default variant as required by Shopify's API
            $data['product']['variants'] = [
                [
                    'sku' => $productSku,
                ],
            ];
        }

        Log::info('ShopifyDataService: Prepared product data', ['sku' => $productSku, 'images_count' => count($images)]);

        return $data;
    }

    public function prepareVariantData(
        VendorDesignTemplateStoreVariant $variant,
        VendorDesignTemplateStore $storeOverride,
        ?array $sortedOptionNames = null,
        ?array $imageMap = null,
        ?string $fulfillmentServiceHandle = null
    ): array {
        $price = $this->calculatePrice($variant, $storeOverride);
        // Ensure SKU exists
        $varSku = $variant->sku ?: $this->generateVariantSku($variant, $storeOverride);
        if (empty($variant->sku)) {
            $variant->sku = $varSku;
            $variant->save();
        }

        $serviceHandle = is_string($fulfillmentServiceHandle) && $fulfillmentServiceHandle !== ''
            ? $fulfillmentServiceHandle
            : ShopifyFulfillmentService::SERVICE_HANDLE;

        $vendorCost = $this->getRawVendorCost($variant, $storeOverride);

        $data = [
            'price' => (string) $price,
            'sku' => $varSku,
            '_cost' => (string) $vendorCost,
            // inventory_management must be null for non-trackable inventory, even if fulfillment_service is set.
            'inventory_management' => null,
            'fulfillment_service' => $serviceHandle,
            'requires_shipping' => true,
            'weight' => (float) ($storeOverride->template?->product?->weight ?? 0.0),
            'weight_unit' => $this->storeConfigService->get('weight_unit', 'kg'),
        ];
        // Map options
        if ($sortedOptionNames === null) {
            $sortedOptionNames = $this->getSortedOptionNames($storeOverride);
        }

        $options = $this->getVariantOptions($variant, $sortedOptionNames);
        foreach ($options as $index => $optionValue) {
            $data['option'.($index + 1)] = $optionValue;
        }

        // Add image position for ShopifyConnector to resolve image_id
        $imagePosition = $this->getVariantImagePosition($variant, $storeOverride, $imageMap);
        if ($imagePosition) {
            $data['_image_position'] = $imagePosition;
        }

        return ['variant' => $data];
    }

    protected function calculatePrice(VendorDesignTemplateStoreVariant $variant, VendorDesignTemplateStore $storeOverride): ?float
    {
        $vendorCostRaw = $this->getRawVendorCost($variant, $storeOverride);

        // 4. Apply Vendor Markup
        $finalPrice = $this->applyVendorMarkup($variant, $vendorCostRaw);

        // 5. Convert Currency
        if ($storeOverride->connectedStore && $storeOverride->connectedStore->currency) {
            $storeCurrency = $storeOverride->connectedStore->currency;
            $finalPrice = $this->currencyService->convert($finalPrice, $storeCurrency);
        }

        return $finalPrice;
    }

    public function getOrderedImages(VendorDesignTemplateStore $storeOverride): array
    {
        $images = [];
        $filenames = [];

        // 1. Primary Image (Position 1)
        if ($storeOverride->primaryImage?->image_path) {
            $path = $storeOverride->primaryImage->image_path;
            $images[] = [
                'path' => $path,
                'src' => Storage::url($path),
            ];
            $filenames[basename($path)] = true;
        }

        // 2. Sync Images (Position 2+)
        foreach ($storeOverride->syncImages as $image) {
            if ($image->image_path) {
                $path = $image->image_path;
                $filename = basename($path);
                if (! isset($filenames[$filename])) {
                    $images[] = [
                        'path' => $path,
                        'src' => Storage::url($path),
                    ];
                    $filenames[$filename] = true;
                }
            }
        }

        // 3. Variant Specific Design Images (if not already included)
        // Iterate all variants to find any design images that are needed but missing from SyncImages
        if ($storeOverride->template && $storeOverride->template->designImages) {
            $designImages = $storeOverride->template->designImages;

            foreach ($storeOverride->variants as $variant) {
                $product = $variant->product;
                if (! $product) {
                    continue;
                }

                $matchingImage = null;

                // Try match by variant_id
                $matchingImage = $designImages->firstWhere('variant_id', $product->id);

                // If not found, match by color_id
                if (! $matchingImage && $product->attributes) {
                    $colorAttr = $product->attributes->first(function ($attr) {
                        return Str::lower(optional($attr->attribute)->attribute_code ?? '') === 'color';
                    });

                    if ($colorAttr && $colorAttr->option) {
                        $matchingImage = $designImages->firstWhere('color_id', $colorAttr->option->option_id);
                    }
                }

                if ($matchingImage && $matchingImage->image) {
                    $path = $matchingImage->image;
                    $filename = basename($path);

                    if (! isset($filenames[$filename])) {
                        $images[] = [
                            'path' => $path,
                            'src' => Storage::url($path),
                        ];
                        $filenames[$filename] = true;
                    }
                }
            }
        }

        return $images;
    }

    protected function prepareImages(VendorDesignTemplateStore $storeOverride): array
    {
        $orderedImages = $this->getOrderedImages($storeOverride);
        $payload = [];

        foreach ($orderedImages as $index => $img) {
            $payload[] = [
                'src' => $img['src'],
                'position' => $index + 1,
            ];
        }

        return $payload;
    }

    public function getSortedOptionNames(VendorDesignTemplateStore $storeOverride): array
    {
        $optionNamesMap = [];
        foreach ($storeOverride->variants as $variant) {
            $product = $variant->product;
            if ($product && $product->attributes) {
                foreach ($product->attributes as $prodAttr) {
                    if (! $prodAttr->attribute || ! $prodAttr->option) {
                        continue;
                    }

                    $attrName = $prodAttr->attribute->description?->name ?? $prodAttr->attribute->attribute_code ?? 'Option';
                    $optionNamesMap[$attrName] = true;
                }
            }
        }
        $optionNames = array_keys($optionNamesMap);
        sort($optionNames);

        return $optionNames;
    }

    protected function prepareOptions(VendorDesignTemplateStore $storeOverride): array
    {
        $sortedNames = $this->getSortedOptionNames($storeOverride);
        $optionsMap = array_fill_keys($sortedNames, []);

        foreach ($storeOverride->variants as $variant) {
            $product = $variant->product;
            if ($product && $product->attributes) {
                foreach ($product->attributes as $prodAttr) {
                    if (! $prodAttr->attribute || ! $prodAttr->option) {
                        continue;
                    }

                    $attrName = $prodAttr->attribute->description?->name ?? $prodAttr->attribute->attribute_code ?? 'Option';
                    $optionName = $prodAttr->option->key ?? '';

                    if (isset($optionsMap[$attrName]) && ! in_array($optionName, $optionsMap[$attrName])) {
                        $optionsMap[$attrName][] = $optionName;
                    }
                }
            }
        }

        $options = [];
        foreach ($optionsMap as $name => $values) {
            $options[] = [
                'name' => $name,
                'values' => $values,
            ];
        }

        return $options;
    }

    public function getVariantOptions(VendorDesignTemplateStoreVariant $variant, array $sortedOptionNames): array
    {
        $options = [];
        $variantAttributes = [];

        $product = $variant->product;
        if ($product && $product->attributes) {
            foreach ($product->attributes as $prodAttr) {
                if (! $prodAttr->attribute || ! $prodAttr->option) {
                    continue;
                }
                $attrName = $prodAttr->attribute->description?->name ?? $prodAttr->attribute->attribute_code ?? 'Option';
                $variantAttributes[$attrName] = $prodAttr->option->key ?? '';
            }
        }

        foreach ($sortedOptionNames as $name) {
            $options[] = $variantAttributes[$name] ?? '';
        }

        return $options;
    }

    public function getRawVendorCost(VendorDesignTemplateStoreVariant $variant, VendorDesignTemplateStore $storeOverride): float
    {
        if (! $variant->product) {
            return 0.0;
        }

        $template = $storeOverride->template ?? null;

        // 1. Get Base Price
        $priceObj = null;
        if ($template && $template->manufacturingFactory) {
            $priceObj = $variant->product->pricesWithMargin->firstWhere('factory_id', $template->manufacturingFactory->id);
        }
        if (! $priceObj) {
            $priceObj = $variant->product->pricesWithMargin->first();
        }

        $basePrice = $priceObj ? ($priceObj->base_sale_price ?? $priceObj->base_regular_price ?? 0) : 0;

        // 2. Add Printing Cost
        $printingCost = $this->calculatePrintingCost($template);

        // 3. Add Hang Tag & Packaging Label Cost
        $packagingAddon = $this->calculatePackagingAddon($storeOverride);

        $totalBase = $basePrice + $printingCost + $packagingAddon;

        // 4. Apply Admin Markup
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
            return $totalBase; // avoid division by zero
        } else {
            return $totalBase / (1 - $marginDecimal);
        }
    }

    protected function calculatePackagingAddon(VendorDesignTemplateStore $storeOverride): float
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

    protected function calculatePrintingCost($template): float
    {
        $printingCost = 0.0;
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

        return $printingCost;
    }

    protected function applyVendorMarkup(VendorDesignTemplateStoreVariant $variant, float $vendorCost): float
    {
        $markupType = $variant->markup_type ?? 'fixed';
        $markupValue = is_numeric($variant->markup ?? null) ? (float) $variant->markup : 0.0;

        if ($markupType === 'percentage' && $markupValue > 0) {
            // Margin Formula: Price = Cost / (1 - (Margin% / 100))
            if ($markupValue >= 100) {
                // If margin is effectively 100% or more (which is invalid for margin), treat as markup
                $fallbackPrice = $vendorCost + ($vendorCost * ($markupValue / 100));

                Log::warning('ShopifyDataService::applyVendorMarkup - Invalid margin percentage treated as markup', [
                    'markup_value' => $markupValue,
                    'vendor_cost' => $vendorCost,
                    'calculated_price' => $fallbackPrice,
                    'variant_id' => $variant->id ?? 'unknown',
                ]);

                return $fallbackPrice;
            } else {
                return $vendorCost / (1 - ($markupValue / 100));
            }
        }

        return $vendorCost + $markupValue;
    }

    protected function generateProductSku(VendorDesignTemplateStore $storeOverride): string
    {
        // Generate numeric-only SKU based on time and ID
        return date('YmdHis').$storeOverride->id;
    }

    public function generateVariantSku(VendorDesignTemplateStoreVariant $variant, VendorDesignTemplateStore $storeOverride): string
    {
        $baseSku = $storeOverride->sku;

        if (empty($baseSku)) {
            $baseSku = $this->generateProductSku($storeOverride);
        }

        return $baseSku.'-'.$variant->id;
    }

    public function getVariantImagePosition(VendorDesignTemplateStoreVariant $variant, VendorDesignTemplateStore $storeOverride, ?array $imageMap = null): ?int
    {
        $product = $variant->product;
        if (! $product) {
            return null;
        }

        if ($imageMap === null) {
            $imageMap = $this->getImagePositionMap($storeOverride);
        }

        if ($storeOverride->template && $storeOverride->template->designImages) {
            $designImages = $storeOverride->template->designImages;

            // 1. Try match by variant_id
            $matchingImage = $designImages->firstWhere('variant_id', $product->id);

            // 2. If not found, match by color_id
            if (! $matchingImage && $product->attributes) {
                $colorAttr = $product->attributes->first(function ($attr) {
                    return Str::lower(optional($attr->attribute)->attribute_code ?? '') === 'color';
                });

                if ($colorAttr && $colorAttr->option) {
                    $matchingImage = $designImages->firstWhere('color_id', $colorAttr->option->option_id);
                }
            }

            if ($matchingImage && $matchingImage->image) {
                $designFilename = basename($matchingImage->image);

                return $imageMap[$designFilename] ?? null;
            }
        }

        return null;
    }

    public function getVariationBatches(VendorDesignTemplateStore $storeOverride, ?int $batchSize = null, ?string $fulfillmentServiceHandle = null): array
    {
        $batchSize = $batchSize ?? config('shopify.sync.variation_batch_size', 50);
        $allVariations = $this->prepareVariationsData($storeOverride, $fulfillmentServiceHandle);

        $createQueue = $allVariations['create'];
        $updateQueue = $allVariations['update'];

        $createCount = count($createQueue);
        $updateCount = count($updateQueue);

        $createIdx = 0;
        $updateIdx = 0;

        $batches = [];

        while ($createIdx < $createCount || $updateIdx < $updateCount) {
            $currentBatchCreate = [];
            $currentBatchUpdate = [];
            $currentCount = 0;

            // Fill with updates first
            while ($updateIdx < $updateCount && $currentCount < $batchSize) {
                $currentBatchUpdate[] = $updateQueue[$updateIdx];
                $updateIdx++;
                $currentCount++;
            }

            // Fill remaining space with creates
            while ($createIdx < $createCount && $currentCount < $batchSize) {
                $currentBatchCreate[] = $createQueue[$createIdx];
                $createIdx++;
                $currentCount++;
            }

            $batches[] = [
                'create' => $currentBatchCreate,
                'update' => $currentBatchUpdate,
            ];
        }

        return $batches;
    }

    public function prepareVariationsData(VendorDesignTemplateStore $storeOverride, ?string $fulfillmentServiceHandle = null): array
    {
        $create = [];
        $update = [];

        $sortedOptionNames = $this->getSortedOptionNames($storeOverride);
        $imageMap = $this->getImagePositionMap($storeOverride);

        foreach ($storeOverride->variants as $variant) {
            try {
                $variationData = $this->prepareVariantData(
                    $variant,
                    $storeOverride,
                    $sortedOptionNames,
                    $imageMap,
                    $fulfillmentServiceHandle
                );
                $innerData = $variationData['variant'];

                if ($variant->external_variant_id) {
                    $innerData['id'] = (int) $variant->external_variant_id;
                    $update[] = $innerData;
                } else {
                    $create[] = $innerData;
                }
            } catch (\Throwable $e) {
                Log::error('Error preparing Shopify single variation data', [
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

    public function getImagePositionMap(VendorDesignTemplateStore $storeOverride): array
    {
        $orderedImages = $this->getOrderedImages($storeOverride);
        $map = [];

        foreach ($orderedImages as $index => $img) {
            $filename = basename($img['path']);
            $map[$filename] = $index + 1;
        }

        return $map;
    }
}
