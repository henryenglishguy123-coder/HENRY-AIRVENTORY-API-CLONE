<?php

namespace App\Http\Controllers\Api\V1\Catalog\Category;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Catalog\Category\CategoryResource;
use App\Models\Catalog\Category\CatalogCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class CategoryController extends Controller
{
    protected const CACHE_KEY = 'catalog_categories_all';

    protected const CACHE_TTL = 3600; // 1 hour

    public function index(Request $request): JsonResponse
    {
        $slug = $request->query('slug');
        $ttl = self::CACHE_TTL;

        /**
         * SLUG BASED REQUEST
         */
        if ($slug) {
            $cacheKey = self::CACHE_KEY.'_slug_'.$slug;

            $categories = Cache::remember($cacheKey, $ttl, function () use ($slug) {
                $category = CatalogCategory::query()
                    ->where('slug', $slug)
                    ->where(function ($q) {
                        $q->whereHas('products')
                            ->orWhereHas('children.products');
                    })
                    ->select(['id', 'slug', 'parent_id'])
                    ->with([
                        'meta:id,catalog_category_id,name,image',
                        'parent.meta:id,catalog_category_id,name,image',
                        'children' => function ($query) {
                            $query->select(['id', 'slug', 'parent_id'])
                                ->where(function ($q) {
                                    $q->whereHas('products')
                                        ->orWhereHas('children.products');
                                })
                                ->with([
                                    'meta:id,catalog_category_id,name,image',
                                    'children' => function ($sub) {
                                        $sub->select(['id', 'slug', 'parent_id'])
                                            ->with([
                                                'meta:id,catalog_category_id,name,image',
                                                'children', // recursive
                                            ]);
                                    },
                                ]);
                        },
                    ])
                    ->first();

                if (! $category) {
                    return collect();
                }

                // If children exist, return children; else return the category itself.
                return $category->children->isNotEmpty()
                    ? $category->children
                    : collect([$category]);
            });

            return response()->json([
                'success' => true,
                'data' => CategoryResource::collection($categories),
                'message' => __('Category fetched successfully.'),
            ], Response::HTTP_OK)->header('X-Cache-TTL', $ttl);
        }

        /**
         * ROOT CATEGORIES REQUEST
         */
        $rootCacheKey = self::CACHE_KEY.'_root';

        $categories = Cache::remember($rootCacheKey, $ttl, function () {
            return CatalogCategory::query()
                // ->where(function ($q) {
                //     $q->whereHas('products')
                //         ->orWhereHas('children.products');
                // })
                ->select(['id', 'slug', 'parent_id'])
                ->with([
                    'meta:id,catalog_category_id,name,image',
                    'children' => function ($query) {
                        $query->select(['id', 'slug', 'parent_id'])
                            ->where(function ($q) {
                                $q->whereHas('products')
                                    ->orWhereHas('children.products');
                            })
                            ->with([
                                'meta:id,catalog_category_id,name,image',
                                'children' => function ($sub) {
                                    $sub->select(['id', 'slug', 'parent_id'])
                                        ->with([
                                            'meta:id,catalog_category_id,name,image',
                                            'children', // recursive
                                        ]);
                                },
                            ]);
                    },
                ])
                ->get();
        });

        return response()->json([
            'success' => true,
            'data' => CategoryResource::collection($categories),
            'message' => __('Categories fetched successfully.'),
        ], Response::HTTP_OK)->header('X-Cache-TTL', $ttl);
    }
}
