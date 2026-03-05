<?php

namespace App\Http\Controllers\Api\V1\Catalog\Category;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Catalog\Category\CategoryDetailsResource;
use App\Models\Catalog\Category\CatalogCategory;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class CategoryDetailsController extends Controller
{
    protected const CACHE_KEY = 'catalog.category.details.%s';

    protected const CACHE_TTL = 3600; // seconds

    public function show(Request $request, string $slug): JsonResponse
    {
        $cacheKey = sprintf(self::CACHE_KEY, $slug);

        try {
            $payload = Cache::remember(
                $cacheKey,
                self::CACHE_TTL,
                function () use ($slug, $request) {
                    $category = CatalogCategory::query()
                        ->select(['id', 'slug', 'parent_id'])
                        ->with([
                            'meta:id,catalog_category_id,name,description,image',
                            'children' => function ($query) {
                                $query->select(['id', 'slug', 'parent_id'])
                                    ->with('meta:id,catalog_category_id,name,image');
                            },
                        ])
                        ->where('slug', $slug)
                        ->firstOrFail();

                    return [
                        'success' => true,
                        'message' => __('Category details fetched successfully.'),
                        'data' => (new CategoryDetailsResource($category))->toArray($request),
                    ];
                }
            );

            return response()->json($payload, Response::HTTP_OK);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => __('Category not found.'),
            ], Response::HTTP_NOT_FOUND);
        }
    }
}
