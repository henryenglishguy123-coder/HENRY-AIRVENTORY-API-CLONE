<?php

namespace App\Http\Resources\Api\V1\Customer\Template;

use App\Services\Template\TemplateDetailsService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class VendorDesignTemplateResource extends JsonResource
{
    public function __construct($resource, private ?TemplateDetailsService $templateService = null)
    {
        parent::__construct($resource);
        $this->templateService ??= app(TemplateDetailsService::class);
    }

    /**
     * Transform the resource into an array with optimized queries.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $storeId = $request->input('store_id');
        $storeOverride = null;
        $storeVariants = collect();

        // Get store override from already-loaded relationship
        if ($this->relationLoaded('storeOverrides')) {
            $storeOverride = $this->storeOverrides->first();
            $storeVariants = $storeOverride?->variants->keyBy('catalog_product_id') ?? collect();
        }

        $factory = $this->manufacturingFactory;
        $packaging = $factory?->packagingLabel;
        $hangTag = $factory?->hangTag;

        // Extract base markup from variant prices
        $variantPrices = $this->templateService->getVariantPrices(
            $this->resource,
            $factory?->id
        );

        $baseMarkup = null;
        foreach ($variantPrices as $vp) {
            if (is_array($vp) && array_key_exists('markup_percentage', $vp) && $vp['markup_percentage'] !== null) {
                $baseMarkup = $vp['markup_percentage'];
                break;
            }
        }

        // $baseMarkup represents a margin percentage (e.g., 50 for 50%).
        // Selling Price = Cost / (1 - Margin %)
        $calcPriceWithMargin = function ($price) use ($baseMarkup) {
            if ($price === null) {
                return null;
            }

            $cost = (float) $price;
            $marginFraction = ((float) $baseMarkup) / 100;

            // Prevent division by zero if margin is 100% or greater.
            if ($marginFraction >= 1) {
                return round($cost, 2);
            }

            return round($cost / (1 - $marginFraction), 2);
        };

        // Build response array
        $data = [
            'id' => $this->id,
            'name' => $this->getTemplateName($storeOverride),
            'description' => $this->getTemplateDescription($storeOverride),
            'sync_status' => $storeOverride?->sync_status,
            'has_sync_error' => (bool) ($storeOverride?->sync_error),
            'external_product_id' => $storeOverride?->external_product_id,
            'is_link_only' => $storeOverride ? (bool) ($storeOverride->is_link_only ?? false) : null,
            'hang_tag_id' => $storeOverride?->hang_tag_id,
            'packaging_label_id' => $storeOverride?->packaging_label_id,
            'primary_image' => $this->getPrimaryImage($storeOverride),
            'sync_images' => $this->getSyncImages($storeOverride),
            'catalog_design_template_id' => $this->catalog_design_template_id,
            'factory_packaging_label' => $packaging ? [
                'id' => $packaging->factory_id,
                'front_price' => $calcPriceWithMargin($packaging->front_price),
                'back_price' => $calcPriceWithMargin($packaging->back_price),
                'is_active' => $packaging->is_active,
                'branding_images' => $storeOverride?->packagingLabelBranding ? [
                    'front' => $storeOverride->packagingLabelBranding->image ? Storage::url($storeOverride->packagingLabelBranding->image) : null,
                    'back' => $storeOverride->packagingLabelBranding->image_back ? Storage::url($storeOverride->packagingLabelBranding->image_back) : null,
                ] : null,
            ] : null,
            'factory_hang_tag' => $hangTag ? [
                'id' => $hangTag->factory_id,
                'front_price' => $calcPriceWithMargin($hangTag->front_price),
                'back_price' => $calcPriceWithMargin($hangTag->back_price),
                'is_active' => $hangTag->is_active,
                'branding_images' => $storeOverride?->hangTagBranding ? [
                    'front' => $storeOverride->hangTagBranding->image ? Storage::url($storeOverride->hangTagBranding->image) : null,
                    'back' => $storeOverride->hangTagBranding->image_back ? Storage::url($storeOverride->hangTagBranding->image_back) : null,
                ] : null,
            ] : null,
            'product' => $this->getProductData($storeVariants, $variantPrices, $baseMarkup),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];

        return $data;
    }

    /**
     * Get template name from store override or base information.
     */
    private function getTemplateName($storeOverride): ?string
    {
        if ($storeOverride && $storeOverride->name) {
            return $storeOverride->name;
        }

        // Check if information relationship is loaded and return the name, otherwise null
        if ($this->relationLoaded('information') && $this->information) {
            return $this->information->name;
        }

        return null;
    }

    /**
     * Get template description from store override or base information.
     */
    private function getTemplateDescription($storeOverride): ?string
    {
        if ($storeOverride && $storeOverride->description) {
            return $storeOverride->description;
        }

        // Check if information relationship is loaded and return the description, otherwise null
        if ($this->relationLoaded('information') && $this->information) {
            return $this->information->description;
        }

        return null;
    }

    /**
     * Get primary image data.
     * OPTIMIZATION: Use direct S3 URL generation, skip accessor calls that hit S3.
     */
    private function getPrimaryImage($storeOverride): ?array
    {
        if (! $storeOverride || ! $storeOverride->relationLoaded('primaryImage')) {
            return null;
        }

        $image = $storeOverride->primaryImage;
        if (! $image) {
            return null;
        }

        return [
            'id' => $image->id,
            'image' => $this->templateService->generateDirectImageUrl($image->image_path),
        ];
    }

    /**
     * Get sync images data.
     * OPTIMIZATION: Use direct S3 URL generation, skip accessor calls that hit S3.
     */
    private function getSyncImages($storeOverride): array
    {
        if (! $storeOverride || ! $storeOverride->relationLoaded('syncImages')) {
            return [];
        }

        return $storeOverride->syncImages
            ->map(fn ($img) => [
                'id' => $img->id,
                'image' => $this->templateService->generateDirectImageUrl($img->image_path),
                'is_primary' => false,
            ])
            ->values()
            ->toArray();
    }

    /**
     * Get product data with optimized variant pricing.
     */
    private function getProductData($storeVariants, array $variantPrices, ?float $baseMarkup): ?array
    {
        if (! $this->relationLoaded('product') || ! $this->product) {
            return null;
        }

        $info = $this->product->info;
        $description = $this->extractProductDescription($info);

        // Get grouped design images
        $designImages = $this->relationLoaded('designImages')
            ? $this->templateService->groupDesignImages($this->resource)
            : [];

        return [
            'id' => $this->product->id,
            'name' => optional($info)->name,
            'sku' => $this->product->sku,
            'description' => $description,
            'markup_percentage' => $baseMarkup,
            'variations' => $this->getProductVariations(
                $storeVariants,
                $variantPrices,
                $designImages
            ),
        ];
    }

    /**
     * Extract clean product description.
     */
    private function extractProductDescription($info): ?string
    {
        if (! $info) {
            return null;
        }

        $cleanDescription = trim(strip_tags($info->description ?? ''));

        return $cleanDescription !== '' ? $info->description : ($info->short_description ?? null);
    }

    /**
     * Get product variations with optimized price and image lookups.
     */
    private function getProductVariations(
        $storeVariants,
        array $variantPrices,
        array $designImages
    ): array {
        if (! $this->relationLoaded('product') || ! $this->product->relationLoaded('children')) {
            return [];
        }

        return $this->product->children
            ->map(fn ($variant) => $this->buildVariationData(
                $variant,
                $storeVariants,
                $variantPrices,
                $designImages
            ))
            ->values()
            ->toArray();
    }

    /**
     * Build single variation data object.
     */
    private function buildVariationData(
        $variant,
        $storeVariants,
        array $variantPrices,
        array $designImages
    ): array {
        $storeVariant = $storeVariants->get($variant->id);
        $priceData = $variantPrices[$variant->id] ?? null;

        // Try to get preview images by variant_id first, then by color as fallback
        $previewImages = $designImages[$variant->id] ?? [];
        if (empty($previewImages) && $variant->relationLoaded('attributes')) {
            // Find color option ID once and use it (OPTIMIZATION: cache color lookup)
            $colorId = $this->getVariantColorId($variant);
            if ($colorId) {
                $previewImages = $designImages["color_{$colorId}"] ?? [];
            }
        }

        return [
            'id' => $variant->id,
            'is_selected' => $storeVariant ? (bool) $storeVariant->is_enabled : false,
            'markup' => $storeVariant?->markup,
            'markup_type' => $storeVariant?->markup_type ?? 'percentage',
            'sku' => $storeVariant?->sku ?? $variant->sku,
            'price' => [
                'raw_price' => $priceData['raw_price'] ?? null,
                'price' => $priceData['formatted_price'] ?? null,
            ],
            'options' => $this->getVariantOptions($variant),
            'preview_images' => $previewImages,
        ];
    }

    /**
     * Extract variant color ID efficiently (OPTIMIZATION: cached lookup).
     */
    private function getVariantColorId($variant): ?int
    {
        if (! $variant->relationLoaded('attributes') || $variant->attributes->isEmpty()) {
            return null;
        }

        // Cache attributes in a simple array to avoid repeated filtering
        static $colorCache = [];
        $variantId = $variant->id;

        if (! isset($colorCache[$variantId])) {
            $colorAttribute = null;
            foreach ($variant->attributes as $attr) {
                if ($attr->attribute?->attribute_code === 'color') {
                    $colorAttribute = $attr;
                    break;
                }
            }
            $colorCache[$variantId] = $colorAttribute?->option?->option_id;
        }

        return $colorCache[$variantId];
    }

    /**
     * Get variant options/attributes.
     */
    private function getVariantOptions($variant): array
    {
        if (! $variant->relationLoaded('attributes')) {
            return [];
        }

        return $variant->attributes
            ->map(fn ($attr) => [
                'id' => $attr->attribute_option_id ?? $attr->option?->option_id,
                'key' => $attr->attribute?->attribute_code ?? $attr->option?->key,
                'value' => $attr->option?->option_value,
                'code' => $attr->option?->key,
            ])
            ->values()
            ->toArray();
    }
}
