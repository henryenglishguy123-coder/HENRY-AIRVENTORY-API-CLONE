<?php

namespace App\Http\Controllers\Api\V1\Catalog\Product;

use App\Http\Controllers\Controller;
use App\Models\Catalog\Product\CatalogProduct;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class ProductFilterController extends Controller
{
    protected int $ttl = 600;

    public function index(): JsonResponse
    {
        $cacheKey = 'product_filters_v2';
        $data = Cache::remember($cacheKey, $this->ttl, function () {
            $products = CatalogProduct::query()
                ->whereHas('designTemplate')
                ->where('status', 1)
                ->where('type', 'configurable')
                ->with([
                    'children:id',
                    'children.pricesWithMargin:id,catalog_product_id,sale_price,regular_price,specific_markup',
                    'children.attributes' => function ($query) {
                        $query->whereHas('attribute', fn ($q) => $q->where('use_for_filter', 1));
                    },
                    'children.attributes.attribute:attribute_id,attribute_code,use_for_filter',
                    'children.attributes.option:option_id,key,option_value',
                ])
                ->get();
            /**
             * PRICE RANGE
             */
            $priceCollection = $products->pluck('children')->flatten()->pluck('pricesWithMargin')->flatten();

            // Calculate effective prices (Sale > Regular)
            $effectivePrices = $priceCollection
                ->map(fn ($price) => $price->effective_price)
                ->filter(fn ($val) => $val > 0);

            $min = $effectivePrices->min();
            $max = $effectivePrices->max();

            /**
             * FILTERABLE ATTRIBUTES
             */
            $attributes = [];

            foreach ($products as $product) {
                foreach ($product->children as $child) {
                    foreach ($child->attributes as $attr) {
                        $attribute = $attr->attribute;
                        $option = $attr->option;
                        if (! $attribute) {
                            continue;
                        }
                        $code = $attribute->attribute_code;
                        $key = $option->key ?? $attr->attribute_value;
                        $value = $option->option_value ?? $key;
                        if (! $code || ! $key || ! $value) {
                            continue;
                        }
                        $attributes[$code][] = [
                            'option_id' => $option->option_id ?? null,
                            'key' => $key,
                            'value' => $value,
                        ];
                    }
                }
            }

            // remove dupes per code
            foreach ($attributes as $code => $items) {
                $attributes[$code] = collect($items)->unique('key')->values()->toArray();
            }

            return [
                'price_range' => [
                    'min' => $min !== null ? round($min, 2) : null,
                    'max' => $max !== null ? round($max, 2) : null,
                ],
                'attributes' => $attributes,
            ];
        });

        return response()
            ->json(['data' => $data], Response::HTTP_OK)
            ->withHeaders([
                'Cache-Control' => "public, max-age={$this->ttl}",
                'Expires' => now()->addSeconds($this->ttl)->toRfc7231String(),
                'X-Cache-TTL' => $this->ttl,
                'ETag' => md5(json_encode($data)),
            ]);
    }
}
