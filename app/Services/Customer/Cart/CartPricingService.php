<?php

namespace App\Services\Customer\Cart;

use App\Models\Catalog\Product\CatalogProduct;
use App\Models\Customer\Branding\VendorDesignBranding;
use App\Models\Customer\Designer\VendorDesignTemplate;
use App\Models\Factory\HangTag;
use App\Models\Factory\PackagingLabel;
use App\Services\StoreConfigService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class CartPricingService
{
    public function __construct(
        protected InventoryService $inventoryService,
        protected PrintingCostService $printingCostService
    ) {}

    public function resolveUnitPrice(
        CatalogProduct $variant,
        VendorDesignTemplate $template,
        ?\App\Models\Customer\Designer\VendorDesignTemplateStore $storeOverride = null
    ): float {
        $prices = $variant->pricesWithMargin()->get();
        if ($prices->isEmpty()) {
            throw ValidationException::withMessages([
                'product_id' => __('Pricing for the selected product is not available.'),
            ]);
        }
        $factoryId = $this->inventoryService->findFactoryWithStock($variant, $template);
        $basePrice = $this->getBaseProductPrice($prices, $factoryId);
        $printingCost = $factoryId ? $this->printingCostService->calculatePrintingCost($variant, $template, $factoryId) : 0.0;
        $printingCostWithMarkup = $this->applyGlobalMarkup($printingCost);

        $brandingCost = $this->calculateBrandingCost($template, $factoryId, null, null, $storeOverride);
        $brandingCostWithMarkup = $this->applyGlobalMarkup($brandingCost);

        return $basePrice + $printingCostWithMarkup + $brandingCostWithMarkup;
    }

    public function calculatePriceForFactory(
        CatalogProduct $variant,
        VendorDesignTemplate $template,
        int $factoryId
    ): float {
        $prices = $variant->pricesWithMargin()->get();
        if ($prices->isEmpty()) {
            throw ValidationException::withMessages([
                'product_id' => __('Pricing for the selected product is not available.'),
            ]);
        }

        $basePrice = $this->getBaseProductPrice($prices, $factoryId);
        $printingCost = $this->printingCostService->calculatePrintingCost($variant, $template, $factoryId);
        $printingCostWithMarkup = $this->applyGlobalMarkup($printingCost);

        $brandingCost = $this->calculateBrandingCost($template, $factoryId);
        $brandingCostWithMarkup = $this->applyGlobalMarkup($brandingCost);

        return $basePrice + $printingCostWithMarkup + $brandingCostWithMarkup;
    }

    private function getBaseProductPrice(Collection $prices, ?int $factoryId): float
    {
        if ($factoryId) {
            $factoryPrice = $prices->firstWhere('factory_id', $factoryId);
            if ($factoryPrice) {
                $candidate = $factoryPrice->sale_price ?: $factoryPrice->regular_price;
                if ($candidate !== null && $candidate > 0) {
                    return (float) $candidate;
                }
            }
        }
        $lowest = $prices
            ->map(fn ($price) => $price->sale_price ?: $price->regular_price)
            ->filter(fn ($price) => $price > 0)
            ->sort()
            ->first();
        if ($lowest === null || $lowest <= 0) {
            throw ValidationException::withMessages([
                'product_id' => [__('Pricing for the selected product is not available.')],
            ]);
        }

        return (float) $lowest;
    }

    private function applyGlobalMarkup(float $amount): float
    {
        if ($amount <= 0) {
            return 0.0;
        }
        $marginPercent = max(0, (float) app(StoreConfigService::class)->get('profit_global_markup'));
        $marginFraction = $marginPercent / 100;

        if ($marginFraction >= 1) {
            return $amount;
        }

        return $amount / (1 - $marginFraction);
    }

    public function getFulfillmentFactoryId(
        CatalogProduct $variant,
        VendorDesignTemplate $template
    ): ?int {
        return $this->inventoryService->findFactoryWithStock($variant, $template);
    }

    public function getFactoriesWithStock(CatalogProduct $product): Collection
    {
        return $this->inventoryService
            ->getFactoryStockInfo($product)
            ->filter(fn ($info) => $info['in_stock'])
            ->values();
    }

    /**
     * Calculate branding cost for a template at a given factory.
     *
     * Accepts optional pre-fetched factory packaging/hang tag records to avoid
     * repeated DB queries when called in a loop (e.g., during order import).
     */
    public function calculateBrandingCost(
        VendorDesignTemplate $template,
        ?int $factoryId,
        ?PackagingLabel $factoryPackaging = null,
        ?HangTag $factoryHangTag = null,
        ?\App\Models\Customer\Designer\VendorDesignTemplateStore $storeOverride = null
    ): float {
        if (! $factoryId) {
            return 0.0;
        }

        $branding = $storeOverride ?? $template->store_branding;
        if (! $branding) {
            return 0.0;
        }

        $cost = 0.0;

        if ($branding->packaging_label_id) {
            $packagingLabel = VendorDesignBranding::find($branding->packaging_label_id);
            $resolvedFactoryPackaging = $factoryPackaging ?? PackagingLabel::where('factory_id', $factoryId)->orderBy('id', 'desc')->first();

            if (! $packagingLabel || ! $resolvedFactoryPackaging) {
                Log::warning('Branding pricing: packaging label record missing', [
                    'packaging_label_id' => $branding->packaging_label_id,
                    'factory_id' => $factoryId,
                    'template_id' => $template->id,
                    'packaging_label_found' => (bool) $packagingLabel,
                    'factory_packaging_found' => (bool) $resolvedFactoryPackaging,
                ]);
            }

            if ($packagingLabel && $resolvedFactoryPackaging) {
                if ($packagingLabel->image) {
                    $cost += (float) $resolvedFactoryPackaging->front_price;
                }
                if ($packagingLabel->image_back) {
                    $cost += (float) $resolvedFactoryPackaging->back_price;
                }
            }
        }

        if ($branding->hang_tag_id) {
            $hangTag = VendorDesignBranding::find($branding->hang_tag_id);
            $resolvedFactoryHangTag = $factoryHangTag ?? HangTag::where('factory_id', $factoryId)->orderBy('id', 'desc')->first();

            if (! $hangTag || ! $resolvedFactoryHangTag) {
                Log::warning('Branding pricing: hang tag record missing', [
                    'hang_tag_id' => $branding->hang_tag_id,
                    'factory_id' => $factoryId,
                    'template_id' => $template->id,
                    'hang_tag_found' => (bool) $hangTag,
                    'factory_hang_tag_found' => (bool) $resolvedFactoryHangTag,
                ]);
            }

            if ($hangTag && $resolvedFactoryHangTag) {
                if ($hangTag->image) {
                    $cost += (float) $resolvedFactoryHangTag->front_price;
                }
                if ($hangTag->image_back) {
                    $cost += (float) $resolvedFactoryHangTag->back_price;
                }
            }
        }

        return $cost;
    }
}
