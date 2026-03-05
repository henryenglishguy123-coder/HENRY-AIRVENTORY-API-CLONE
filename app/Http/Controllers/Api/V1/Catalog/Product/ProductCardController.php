<?php

namespace App\Http\Controllers\Api\V1\Catalog\Product;

use App\Http\Controllers\Controller;
use App\Models\Catalog\Product\CatalogProduct;
use App\Models\Catalog\Product\CatalogProductPriceWithMargin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class ProductCardController extends Controller
{
    protected int $ttl = 600;

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'currency' => 'nullable|string|size:3|exists:currencies,code',
            'related_product' => 'nullable|boolean',
            'slug' => 'required_if:related_product,true|string|exists:catalog_products,slug',
            'limit' => 'nullable|integer|min:1|max:100',
            'page' => 'nullable|integer|min:1',
            'country' => 'nullable|integer|exists:countries,id',
        ]);

        $isAdmin = Auth::guard('admin_api')->check();

        $params = [
            'limit' => (int) ($request->get('limit', 20)),
            'page' => (int) ($request->get('page', 1)),
            'search' => $request->get('search'),
            'category' => $request->get('category'),
            'sort' => $request->get('sort', 'slug'),
            'price_min' => $request->get('price_min'),
            'price_max' => $request->get('price_max'),
            'attributes' => (array) $request->get('attributes', []),
            'currency' => strtoupper($request->get('currency', 'USD')),
            'related_product' => filter_var($request->get('related_product'), FILTER_VALIDATE_BOOLEAN),
            'slug' => $request->get('slug'),
            'country' => $request->get('country') !== null
                ? (int) $request->get('country')
                : null,
        ];
        if ($isAdmin) {
            $params['status'] = $request->get('status') !== null
                ? filter_var($request->get('status'), FILTER_VALIDATE_BOOLEAN)
                : null;
            $params['template'] = $request->get('template') ?? null;
        }

        $role = $isAdmin ? 'admin' : 'user';

        /** =========================
         *  Cache Key (STABLE)
         * ========================= */
        $normalized = $this->normalizeParams($params);
        $hash = md5(json_encode($normalized));
        $store = Cache::store(config('cache.catalog_store'));
        $version = $store->get("product_card_version:{$role}", 1);

        $cacheKey = "product_card:{$role}:v{$version}:{$hash}:page:{$params['page']}";

        /** =========================
         *  Cache
         * ========================= */
        $cachedData = $store->remember(
            $cacheKey,
            now()->addSeconds($this->ttl),
            function () use ($params, $isAdmin) {

                $query = CatalogProduct::query()
                    ->where('type', 'configurable')
                    ->select([
                        'catalog_products.id',
                        'catalog_products.sku',
                        'catalog_products.status',
                        'catalog_products.slug',
                    ])
                    ->with([
                        'info:id,catalog_product_id,name',
                        'pricesWithMargin:catalog_product_id,sale_price,regular_price,specific_markup',
                        'children:catalog_products.id,sku',
                        'children.pricesWithMargin' => function ($q) {
                            $q->select('id', 'catalog_product_id', 'factory_id', 'sale_price', 'regular_price', 'specific_markup')
                                ->with('factory.business:id,factory_id,country_id');
                        },
                        'children.attributes.attribute',
                        'children.attributes.option',
                        'files' => fn ($q) => $q
                            ->select('catalog_product_id', 'image', 'order')
                            ->where('type', 'image')
                            ->orderBy('order')
                            ->limit(1),
                        'categories',
                        'designTemplate.catalogDesignTemplate:id,status,name',
                        'printingPrices.layer:id,image',
                    ]);

                /** User only filters */
                if (! $isAdmin) {
                    $query->where('status', 1)
                        ->whereHas('designTemplate.catalogDesignTemplate')
                        ->whereHas('layerImages');
                }
                if ($isAdmin) {
                    if ($params['template'] === 'valid') {
                        $query->whereHas('designTemplate.catalogDesignTemplate');
                    }

                    if ($params['template'] === 'invalid') {
                        $query->whereDoesntHave('designTemplate.catalogDesignTemplate');
                    }
                    if ($params['status'] !== null) {
                        $query->where('status', $params['status']);
                    }
                }

                /** Related products */
                if ($params['related_product'] && $params['slug']) {
                    $base = CatalogProduct::query()
                        ->where('slug', $params['slug'])
                        ->with('designTemplate.catalogDesignTemplate:id')
                        ->select('id')
                        ->first();

                    $templateId = $base?->designTemplate?->catalogDesignTemplate?->id;

                    if ($templateId) {
                        $query->whereHas(
                            'designTemplate.catalogDesignTemplate',
                            fn ($q) => $q->where('id', $templateId)
                        )->where('catalog_products.id', '!=', $base->id);
                    }
                }

                /** Category */
                if ($params['category']) {
                    $query->whereHas(
                        'categories',
                        fn ($q) => $q->where('slug', $params['category'])
                    );
                }

                /** Search */
                if ($params['search']) {
                    $query->where(function ($q) use ($params) {
                        $q->whereHas(
                            'info',
                            fn ($i) => $i->where('name', 'like', "%{$params['search']}%")
                        )->orWhere('sku', 'like', "%{$params['search']}%");
                    });
                }

                /** Price filter */
                if ($params['price_min'] !== null || $params['price_max'] !== null) {
                    $globalMarkup = CatalogProductPriceWithMargin::getGlobalMarkup();

                    $query->whereHas('children.pricesWithMargin', function ($q) use ($params, $globalMarkup) {
                        $basePrice = '(CASE WHEN sale_price IS NOT NULL AND sale_price > 0 THEN sale_price ELSE regular_price END)';
                        $marginExp = 'COALESCE(specific_markup, ?)';
                        $priceExp = "CASE WHEN {$marginExp} >= 100 THEN {$basePrice} ELSE {$basePrice} / (1 - ({$marginExp} / 100)) END";
                        if ($params['price_min'] !== null) {
                            $q->whereRaw(
                                "{$priceExp} >= ?",
                                [$globalMarkup, $globalMarkup, $params['price_min']]
                            );
                        }
                        if ($params['price_max'] !== null) {
                            $q->whereRaw(
                                "{$priceExp} <= ?",
                                [$globalMarkup, $globalMarkup, $params['price_max']]
                            );
                        }
                    });
                }
                /** Sorting */
                $allowedSorts = ['slug', 'price_low', 'price_high', 'new'];
                $sort = in_array($params['sort'], $allowedSorts) ? $params['sort'] : 'slug';

                if (in_array($sort, ['price_low', 'price_high'])) {
                    $globalMarkup = CatalogProductPriceWithMargin::getGlobalMarkup();

                    $basePrice = '(CASE 
        WHEN child_price.sale_price IS NOT NULL 
             AND child_price.sale_price > 0 
        THEN child_price.sale_price 
        ELSE child_price.regular_price 
    END)';

                    $marginExp = 'COALESCE(child_price.specific_markup, ?)';

                    $priceExp = "CASE
        WHEN {$marginExp} >= 100 THEN {$basePrice}
        ELSE {$basePrice} / (1 - ({$marginExp} / 100))
    END";

                    $query->join('catalog_product_parents as map', 'map.parent_id', '=', 'catalog_products.id')
                        ->leftJoin('catalog_product_prices as child_price', 'child_price.catalog_product_id', '=', 'map.catalog_product_id')
                        ->addSelect([
                            DB::raw("MIN({$priceExp}) as price_min"),
                            DB::raw("MAX({$priceExp}) as price_max"),
                        ])
                        ->addBinding([$globalMarkup, $globalMarkup, $globalMarkup, $globalMarkup], 'select') // two for MIN, two for MAX
                        ->groupBy(
                            'catalog_products.id',
                            'catalog_products.sku',
                            'catalog_products.status',
                            'catalog_products.slug'
                        )
                        ->orderBy(
                            $sort === 'price_low' ? 'price_min' : 'price_max',
                            $sort === 'price_low' ? 'ASC' : 'DESC'
                        );
                } elseif ($sort === 'new') {
                    $query->orderByDesc('catalog_products.id');
                } else {
                    $query->orderBy('catalog_products.slug');
                }

                /** Attributes */
                foreach ($params['attributes'] as $code => $values) {
                    $query->whereHas('children.attributes', function ($q) use ($code, $values) {
                        $q->whereHas(
                            'attribute',
                            fn ($a) => $a->where('attribute_code', $code)
                        )->whereHas(
                            'option',
                            fn ($o) => $o->whereIn('key', (array) $values)
                        );
                    });
                }

                /** Pagination + Transform */
                $products = $query
                    ->paginate($params['limit'])
                    ->through(fn ($p) => $this->transformProduct($p, $params, $isAdmin));
                $paginatedArray = $products->toArray();
                $paginatedArray['items'] = $paginatedArray['data'];
                unset($paginatedArray['data']);

                return $paginatedArray;

            }
        );

        return response()->json(['data' => $cachedData], Response::HTTP_OK);
    }

    /** =========================
     * Helpers
     * ========================= */
    private function normalizeParams(array $params): array
    {
        unset($params['page']);

        ksort($params);

        if (isset($params['attributes'])) {
            ksort($params['attributes']);
            foreach ($params['attributes'] as &$vals) {
                sort($vals);
            }
        }

        return $params;
    }

    private function transformProduct($product, array $params, bool $isAdmin): array
    {
        $allPrices = $product->children
            ->flatMap(fn ($c) => $c->pricesWithMargin ?? collect());

        $filtered = $allPrices;
        if (! empty($params['country'])) {
            $filtered = $allPrices->filter(function ($p) use ($params) {
                $factory = $p->factory;
                $business = $factory?->business;

                return $business && (int) $business->country_id === (int) $params['country'];
            });
        }

        // If no prices for that country, fall back to all factories
        if ($filtered->isEmpty()) {
            $filtered = $allPrices;
        }

        $prices = $filtered
            ->map(fn ($p) => $p->effective_price)
            ->filter();

        $attributes = [];
        foreach ($product->children as $child) {
            foreach ($child->attributes as $attr) {
                $code = $attr->attribute?->attribute_code;
                $value = $attr->option?->option_value ?? $attr->option?->key;
                if ($code && $value) {
                    $attributes[$code][] = $value;
                }
            }
        }

        foreach ($attributes as &$vals) {
            $vals = array_values(array_unique($vals));
        }

        $data = [
            'name' => $product->info->name ?? null,
            'slug' => $product->slug,
            'image' => getImageUrl(
                optional($product->files->first())->image,
                true,
                ['width' => 400, 'height' => 400, 'format' => 'webp']
            ),
            'from_price' => $prices->min()
                ? format_price($prices->min(), $params['currency'])
                : null,
            'to_price' => $prices->max()
                ? format_price($prices->max(), $params['currency'])
                : null,
            'attributes' => $attributes,
        ];

        if ($isAdmin) {
            $data += [
                'id' => $product->id,
                'sku' => $product->sku,
                'status' => $product->status,
                'template' => $product->getTemplateIntegrityStatus(),
            ];
        }

        return $data;
    }
}
