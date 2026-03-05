<?php

namespace App\Http\Controllers\Api\V1\Catalog\Designer;

use App\Http\Controllers\Controller;
use App\Models\Catalog\Product\CatalogProduct;
use App\Models\Currency\Currency;
use App\Models\Factory\Factory;
use App\Services\StoreConfigService;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class ProductDesignerController extends Controller
{
    protected const CACHE_TTL = 1800;

    protected const LOCK_WAIT = 5;

    protected const LOCK_TTL = 10;

    public function index(Request $request, string $productSlug, ?Factory $factory = null): JsonResponse
    {
        $factoryId = $factory ? $factory->id : null;
        $request->validate([
            'currency' => 'nullable|string|size:3|exists:currencies,code',
        ]);

        $currency = strtoupper($request->get('currency', 'USD'));
        $cacheStore = Cache::store(config('cache.catalog_store'));
        $cacheKey = $this->getCacheKey($productSlug, $factoryId, $currency, $cacheStore);
        $data = $cacheStore->get($cacheKey);
        if ($data !== null) {
            return response()->json([
                'success' => true,
                'message' => __('Product designer fetched successfully.'),
                'data' => $data,
            ], Response::HTTP_OK)
                ->header('Cache-Control', 'public, max-age='.self::CACHE_TTL)
                ->header('X-Cache-Status', 'HIT')
                ->header('Vary', 'Accept-Encoding, Currency');
        }
        try {
            $data = $cacheStore->lock("lock:{$cacheKey}", self::LOCK_TTL)
                ->block(self::LOCK_WAIT, function () use ($cacheStore, $cacheKey, $productSlug, $factoryId, $currency) {
                    $cached = $cacheStore->get($cacheKey);
                    if ($cached !== null) {
                        return $cached;
                    }
                    $fresh = $this->buildDesignerData($productSlug, $factoryId, $currency);
                    if ($fresh !== null) {
                        $cacheStore->put($cacheKey, $fresh, now()->addSeconds(self::CACHE_TTL));
                    }

                    return $fresh;
                });
        } catch (LockTimeoutException) {
            $stale = $cacheStore->get($cacheKey);
            if ($stale !== null) {
                return response()->json([
                    'success' => true,
                    'message' => __('Product designer fetched successfully (stale).'),
                    'data' => $stale,
                ], Response::HTTP_OK)
                    ->header('Cache-Control', 'public, max-age='.self::CACHE_TTL)
                    ->header('X-Cache-Status', 'STALE')
                    ->header('Vary', 'Accept-Encoding, Currency');
            }

            return response()->json([
                'success' => false,
                'message' => __('Server busy, please try again.'),
            ], Response::HTTP_SERVICE_UNAVAILABLE);
        }
        if ($data === null) {
            return response()->json([
                'success' => false,
                'message' => __('Product not found.'),
                'data' => null,
            ], Response::HTTP_NOT_FOUND);
        }

        return response()->json([
            'success' => true,
            'message' => __('Product designer fetched successfully.'),
            'data' => $data,
        ], Response::HTTP_OK)
            ->header('Cache-Control', 'public, max-age='.self::CACHE_TTL)
            ->header('X-Cache-Status', 'MISS')
            ->header('Vary', 'Accept-Encoding, Currency');
    }

    private function buildDesignerData(string $productSlug, ?int $factoryId, string $currency): ?array
    {
        $product = $this->fetchProductWithDesignTemplate($productSlug);
        if (! $product) {
            return null;
        }

        return $this->formatDesignerData($product, $factoryId, $currency);
    }

    private function fetchProductWithDesignTemplate(string $productSlug): ?CatalogProduct
    {
        return CatalogProduct::query()
            ->select([
                'catalog_products.id',
                'catalog_products.slug',
                'catalog_products.status',
                'catalog_products.type',
            ])
            ->whereHas('designTemplate.catalogDesignTemplate')
            ->whereHas('layerImages')
            ->where('slug', $productSlug)
            ->where('status', 1)
            ->where('type', 'configurable')
            ->with([
                'layerImages',
                'printingPrices.printingTechnique',
                'designTemplate:id,catalog_product_id,catalog_design_template_id',
                'designTemplate.catalogDesignTemplate:id,name,status',
                'children.attributes.option',
                'children.pricesWithMargin',
                'designTemplate.catalogDesignTemplate.layers' => function ($query) {
                    $query->select([
                        'id',
                        'catalog_design_template_id',
                        'layer_name',
                        'coordinates',
                        'image',
                        'is_neck_layer',
                    ])->orderBy('id');
                },
            ])
            ->first();
    }

    private function formatDesignerData(CatalogProduct $product, ?int $factoryId, string $currency): array
    {
        $template = $product->designTemplate?->catalogDesignTemplate;
        $lowest_price = $this->getLowestChildPrice($product, $factoryId);
        $printingPrices = $product->printingPrices;
        $globalMarkup = (float) app(StoreConfigService::class)->get('profit_global_markup', 0);

        return [
            'slug' => $product->slug,
            'lowest_price' => [
                'price' => $lowest_price !== null ? format_price($lowest_price, $currency) : format_price(0, $currency),
                'raw_price' => $lowest_price,
            ],
            'printing_techniques' => $this->getAllPrintingTechniques($printingPrices),
            'available_colors' => $this->getAvailableColors($product),
            'template' => [
                'id' => $template?->id,
                'name' => $template?->name,
                'layers' => $this->formatLayers(
                    $template?->layers ?? collect(),
                    $product->layerImages,
                    $this->formatPrintingPrices($printingPrices, $factoryId, $currency, $globalMarkup)
                ),
            ],
            'default_currency' => Currency::getDefaultCurrency()->symbol,
        ];
    }

    private function formatLayers($layers, $productLayerImages, array $printingPricesByLayer): array
    {
        $groupedImages = $productLayerImages->groupBy('catalog_design_template_layer_id');

        return $layers->map(function ($layer) use ($groupedImages, $printingPricesByLayer) {
            $layerImages = $groupedImages->get($layer->id, collect());
            $imagesByOption = $layerImages
                ->groupBy('catalog_attribute_option_id')
                ->map(fn ($imgs) => $imgs->map(fn ($img) => [
                    'id' => $img->id,
                    'image' => getImageUrl($img->image_path),
                ]))
                ->toArray();

            return [
                'id' => $layer->id,
                'name' => $layer->layer_name,
                'coordinates' => $this->normalizeCoordinates($layer->coordinates),
                'mockup_image' => $this->normalizeImage($layer->image),
                'printing_techniques' => $printingPricesByLayer[$layer->id]['techniques'] ?? [],
                'images_by_option' => $imagesByOption,
                'is_neck_layer' => (bool) $layer->is_neck_layer,
            ];
        })->values()->toArray();
    }

    private function normalizeCoordinates($value): ?array
    {
        if (is_array($value)) {
            return $value;
        }
        if (is_string($value)) {
            $decoded = json_decode($value, true);

            return is_array($decoded) ? $decoded : null;
        }

        return null;
    }

    private function normalizeImage($value): string
    {
        if (empty($value)) {
            return '';
        }
        if (is_string($value) && str_starts_with($value, 'data:image/')) {
            if (str_starts_with($value, 'data:image/svg+xml')) {
                return '';
            }

            return $value;
        }

        return getImageUrl($value);
    }

    private function getAvailableColors(CatalogProduct $product): array
    {
        $usedOptionIds = $product->layerImages->pluck('catalog_attribute_option_id')->unique()->flip();
        $colors = [];
        foreach ($product->children as $child) {
            foreach ($child->attributes as $attribute) {
                $option = $attribute->option;
                if ($option && $option->type === 'color' && isset($usedOptionIds[$option->option_id])) {
                    $colors[$option->option_id] = [
                        'id' => $option->option_id,
                        'name' => $option->key,
                        'value' => $option->option_value,
                    ];
                }
            }
        }

        return array_values($colors);
    }

    private function formatPrintingPrices(Collection $printingPrices, ?int $factoryId, string $currency, float $markup): array
    {
        $marginFraction = $markup / 100;
        $isInvalidMargin = $marginFraction >= 1;

        return $printingPrices->groupBy('layer_id')->map(function ($prices) use ($factoryId, $marginFraction, $isInvalidMargin, $currency) {
            $filtered = $factoryId ? $prices->where('factory_id', $factoryId) : collect();
            if ($filtered->isEmpty()) {
                $filtered = $prices->groupBy('printing_technique_id')->map(fn ($group) => $group->sortBy('price')->first());
            }

            return [
                'techniques' => $filtered->filter(fn ($price) => $price->printingTechnique && (int) $price->printingTechnique->status === 1)
                    ->map(function ($price) use ($marginFraction, $isInvalidMargin, $currency) {
                        $cost = (float) $price->price;
                        $finalPrice = $isInvalidMargin ? $cost : ($cost / (1 - $marginFraction));

                        return [
                            'id' => $price->printingTechnique->id,
                            'price' => format_price($finalPrice, $currency),
                            'raw_price' => $finalPrice,
                        ];
                    })
                    ->values(),
            ];
        })
            ->toArray();
    }

    private function getAllPrintingTechniques(Collection $prices): array
    {
        return $prices->filter(fn ($price) => $price->printingTechnique && (int) $price->printingTechnique->status === 1)
            ->map(fn ($price) => [
                'id' => $price->printingTechnique->id,
                'name' => $price->printingTechnique->name,
            ])
            ->unique('id')
            ->values()
            ->toArray();
    }

    private function getCacheKey(string $slug, ?int $factoryId, string $currency, $store): string
    {
        $version = $store->get("product_designer_version:{$slug}", 1);

        return sprintf('product_designer:%s:%s:%s:v%s', $slug, $factoryId ? "factory:{$factoryId}" : 'default', $currency, (int) $version);
    }

    private function getLowestChildPrice(CatalogProduct $product, ?int $factoryId): ?float
    {
        $prices = $product->children->flatMap(fn ($child) => $child->pricesWithMargin ?? collect())->map(function ($price) {
            $finalPrice = ! empty($price->sale_price) && $price->sale_price > 0 ? $price->sale_price : $price->regular_price;

            return [
                'factory_id' => $price->factory_id,
                'price' => (float) $finalPrice,
            ];
        })->filter(fn ($p) => $p['price'] > 0);
        if ($factoryId) {
            $factoryMin = $prices->where('factory_id', $factoryId)->min('price');

            if ($factoryMin !== null) {
                return (float) $factoryMin;
            }
        }

        return $prices->isNotEmpty()
            ? (float) $prices->min('price')
            : null;
    }
}
