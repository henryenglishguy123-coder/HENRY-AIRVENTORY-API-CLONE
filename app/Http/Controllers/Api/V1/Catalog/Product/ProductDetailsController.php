<?php

namespace App\Http\Controllers\Api\V1\Catalog\Product;

use App\Http\Controllers\Controller;
use App\Models\Catalog\Product\CatalogProduct;
use App\Models\Location\Country;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class ProductDetailsController extends Controller
{
    /**
     * Cache TTL in seconds (1 hour)
     */
    protected const CACHE_TTL = 3600;

    public function show(Request $request, string $slug): JsonResponse
    {
        $request->validate([
            'currency' => 'nullable|string|size:3|exists:currencies,code',
            'country' => 'nullable|integer|exists:countries,id',
        ]);

        $currency = $request->get('currency', 'USD');
        $countryRaw = $request->get('country');
        $countryId = $countryRaw !== null ? (int) $countryRaw : null;
        $store = Cache::store(config('cache.catalog_store'));
        $version = $store->get("product_details_version:{$slug}", 1);
        $countryKey = $countryId !== null ? (string) $countryId : 'all';
        $cacheKey = "product_details_{$slug}_{$currency}_{$countryKey}:v{$version}";

        $formattedData = $store->remember($cacheKey, self::CACHE_TTL, function () use ($slug, $currency, $countryId) {
            $product = $this->fetchProductWithRelations($slug);

            if (! $product) {
                return null;
            }

            return $this->formatProduct($product, $currency, $countryId);
        });

        if ($formattedData === null) {
            return response()->json([
                'success' => false,
                'message' => __('Product not found or inactive.'),
            ], Response::HTTP_NOT_FOUND);
        }

        return response()->json([
            'success' => true,
            'data' => $formattedData,
            'message' => __('Product fetched successfully.'),
        ], Response::HTTP_OK);
    }

    /**
     * Fetch product with optimized eager loading
     */
    private function fetchProductWithRelations(string $slug): ?CatalogProduct
    {
        return CatalogProduct::query()
            ->select([
                'catalog_products.id',
                'catalog_products.slug',
                'catalog_products.sku',
                'catalog_products.weight',
                'catalog_products.status',
                'catalog_products.type',
            ])
            ->whereHas('designTemplate')
            ->where('slug', $slug)
            ->where('status', 1)
            ->where('type', 'configurable')
            ->with([
                'files' => function ($query) {
                    $query->select([
                        'catalog_product_id',
                        'image',
                        'order',
                    ])->orderBy('order');
                },
                'children' => function ($query) {
                    $query->select([
                        'catalog_products.id',
                        'catalog_products.slug',
                        'catalog_products.sku',
                        'catalog_products.status',
                    ])
                        ->where('status', 1);
                },
                'children.files' => function ($query) {
                    $query->select([
                        'catalog_product_id',
                        'image',
                        'order',
                    ])->orderBy('order');
                },
                'children.attributes' => function ($query) {
                    $query->select([
                        'catalog_product_id',
                        'catalog_attribute_id',
                        'attribute_value',
                    ]);
                },
                'children.attributes.attribute' => function ($query) {
                    $query->select([
                        'attribute_id',
                        'attribute_code',
                        'field_type',
                    ]);
                },
                'children.attributes.attribute.description' => function ($query) {
                    $query->select([
                        'attribute_id',
                        'name',
                    ]);
                },
                'children.attributes.option' => function ($query) {
                    $query->select([
                        'option_id',
                        'key',
                        'option_value',
                        'type',
                    ]);
                },
                'children.pricesWithMargin' => function ($query) {
                    $query->select([
                        'catalog_product_id',
                        'factory_id',
                        'regular_price',
                        'sale_price',
                    ]);
                },
                'children.pricesWithMargin.factory' => function ($query) {
                    $query->select(['id']);
                },
                'children.pricesWithMargin.factory.business' => function ($query) {
                    $query->select([
                        'factory_id',
                        'company_name',
                        'country_id',
                    ]);
                },
                'attributes' => function ($query) {
                    $query->select([
                        'catalog_product_id',
                        'catalog_attribute_id',
                        'attribute_value',
                    ]);
                },
                'attributes.attribute' => function ($query) {
                    $query->select([
                        'attribute_id',
                        'attribute_code',
                    ]);
                },
                'attributes.attribute.description' => function ($query) {
                    $query->select([
                        'attribute_id',
                        'name',
                    ]);
                },
                'attributes.option' => function ($query) {
                    $query->select([
                        'option_id',
                        'key',
                    ]);
                },
                'info' => function ($query) {
                    $query->select([
                        'catalog_product_id',
                        'name',
                        'short_description',
                        'description',
                        'meta_title',
                        'meta_description',
                    ]);
                },
            ])
            ->first();
    }

    /**
     * Format product data structure
     */
    private function formatProduct(CatalogProduct $product, string $currency, ?int $countryId = null): array
    {
        $weightUnit = app(\App\Services\StoreConfigService::class)->get('weight_unit', 'kg');

        return [
            'slug' => $product->slug,
            'sku' => $product->sku,
            'weight' => $product->weight,
            'weight_unit' => $weightUnit,
            'images' => $this->formatImages($product->files ?? collect()),
            'attributes' => $this->formatAttributes($product->attributes ?? collect()),
            'variations' => $this->formatVariations($product->children ?? collect()),
            'variation_images' => $this->formatColorVariationImages($product->children ?? collect()),
            'factories' => $this->formatFactories($product->children ?? collect(), $currency, $countryId),
            'title' => $product->info?->name ?? '',
            'summary' => e($product->info?->short_description ?? ''),
            'details' => e($product->info?->description ?? ''),
            'meta_title' => $product->info?->meta_title ?? '',
            'meta_description' => $product->info?->meta_description ?? '',
        ];
    }

    /**
     * Format product images
     */
    private function formatImages($files): array
    {
        return $files->map(function ($file) {
            return [
                'url' => $file->url ?? '',
            ];
        })->values()->toArray();
    }

    /**
     * Format product attributes
     */
    private function formatAttributes($attributes): array
    {
        return $attributes->map(function ($attribute) {
            return [
                'code' => $attribute->attribute?->attribute_code ?? '',
                'label' => $attribute->attribute?->description?->name ?? null,
                'key' => $attribute->option?->key ?? '',
            ];
        })->values()->toArray();
    }

    /**
     * Format product variations grouped by factory
     */
    private function formatFactories($children, string $currency, ?int $countryId = null): array
    {
        $allFactories = [];
        $countryFactories = [];
        static $countryNameCache = [];

        foreach ($children as $child) {
            foreach ($child->pricesWithMargin as $price) {
                $factoryId = $price->factory_id;
                $factory = $price->factory;
                $business = $factory?->business;
                $factoryName = $business?->company_name;
                $factoryCountryId = $business?->country_id;

                $locationName = null;
                if ($factoryCountryId) {
                    if (! array_key_exists($factoryCountryId, $countryNameCache)) {
                        $countryNameCache[$factoryCountryId] = Country::find($factoryCountryId)?->name;
                    }
                    $locationName = $countryNameCache[$factoryCountryId];
                }

                if (! $factoryId || ! $factoryName) {
                    continue;
                }

                $finalPrice = (! empty($price->sale_price) && $price->sale_price > 0)
                    ? $price->sale_price
                    : $price->regular_price;

                if (empty($finalPrice) || $finalPrice <= 0) {
                    continue;
                }
                if (! isset($allFactories[$factoryId])) {
                    $allFactories[$factoryId] = [
                        'factory_id' => $factoryId,
                        'factory_name' => $factoryName,
                        'location' => $locationName,
                        'variations' => [],
                    ];
                }
                $allFactories[$factoryId]['variations'][] = [
                    'attributes' => $this->formatVariationAttributes($child->attributes ?? collect()),
                    'price' => format_price($finalPrice, $currency),
                ];

                if ($countryId !== null && $factoryCountryId !== null && (int) $factoryCountryId === (int) $countryId) {
                    if (! isset($countryFactories[$factoryId])) {
                        $countryFactories[$factoryId] = [
                            'factory_id' => $factoryId,
                            'factory_name' => $factoryName,
                            'location' => $locationName,
                            'variations' => [],
                        ];
                    }
                    $countryFactories[$factoryId]['variations'][] = [
                        'attributes' => $this->formatVariationAttributes($child->attributes ?? collect()),
                        'price' => format_price($finalPrice, $currency),
                    ];
                }
            }
        }

        if ($countryId !== null && ! empty($countryFactories)) {
            return $countryFactories;
        }

        return $allFactories ?: [];
    }

    /**
     * Format product variations
     */
    private function formatVariations($children): array
    {
        return $children->map(function ($child) {
            return $this->formatVariationAttributes($child->attributes ?? collect());
        })->values()->toArray();
    }

    private function formatColorVariationImages($children): array
    {
        $colorImages = [];

        foreach ($children as $child) {
            foreach ($child->attributes ?? [] as $attribute) {
                $attributeModel = $attribute->attribute ?? null;
                $option = $attribute->option ?? null;

                if (! $attributeModel || ! $option) {
                    continue;
                }

                if ($attributeModel->attribute_code !== 'color') {
                    continue;
                }

                $colorId = $option->option_id ?? null;

                if (! $colorId || isset($colorImages[$colorId])) {
                    continue;
                }

                $files = $child->files ?? collect();
                $imageUrl = null;

                if ($files->isNotEmpty()) {
                    $firstFile = $files->first();
                    $imageUrl = $firstFile->url ?? null;
                }

                if (! $imageUrl) {
                    continue;
                }

                $colorImages[$colorId] = [
                    'option_id' => $colorId,
                    'code' => 'color',
                    'key' => $option->key ?? '',
                    'value' => $option->option_value ?? '',
                    'image' => $imageUrl,
                ];
            }
        }

        return array_values($colorImages);
    }

    /**
     * Format variation attributes
     */
    private function formatVariationAttributes($attributes): array
    {
        return $attributes->map(function ($attribute) {
            return [
                'option_id' => $attribute->option?->option_id ?? '',
                'code' => $attribute->attribute?->attribute_code ?? '',
                'label' => $attribute->attribute?->description?->name ?? null,
                'field_type' => $attribute->attribute?->field_type ?? '',
                'key' => $attribute->option?->key ?? '',
                'value' => $attribute->option?->option_value ?? '',
                'type' => $attribute->option?->type ?? '',
            ];
        })->values()->toArray();
    }
}
