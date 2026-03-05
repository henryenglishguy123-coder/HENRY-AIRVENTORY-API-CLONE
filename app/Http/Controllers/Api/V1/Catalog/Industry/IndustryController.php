<?php

namespace App\Http\Controllers\Api\V1\Catalog\Industry;

use App\Http\Controllers\Controller;
use App\Models\Catalog\Industry\CatalogIndustry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class IndustryController extends Controller
{
    protected const CACHE_KEY = 'catalog_industries';

    protected const CACHE_TTL = 3600; // seconds

    public function index(Request $request): JsonResponse
    {
        $status = $request->query('status');
        $ttl = self::CACHE_TTL;
        $cacheKey = self::CACHE_KEY.'_all'.(is_null($status) ? '' : '_status_'.(int) $status);
        $industries = Cache::remember($cacheKey, $ttl, function () use ($status) {
            $query = CatalogIndustry::query()
                ->with('meta')
                ->withCount(['category as categories_count']);
            if (! is_null($status)) {
                $query->whereHas('meta', function ($q) use ($status) {
                    $q->where('status', (bool) $status);
                });
            }

            return $query->orderByDesc('id')->get();
        });
        $items = $industries->map(function ($industry) {
            return [
                'id' => $industry->id,
                'slug' => $industry->slug,
                'meta' => [
                    'name' => $industry->meta->name ?? null,
                    'status' => isset($industry->meta->status)
                        ? (bool) $industry->meta->status
                        : null,
                ],
                'categories_count' => (int) ($industry->categories_count ?? 0),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $items,
            'message' => __('Industries fetched successfully.'),
        ], Response::HTTP_OK)
            ->header('Pragma', 'public')
            ->header('Expires', now()->addSeconds($ttl)->toRfc7231String())
            ->header('X-Cache-TTL', $ttl);
    }

    /**
     * GET /api/v1/industries/{id}
     */
    public function show(int $id): JsonResponse
    {
        $ttl = self::CACHE_TTL;
        $cacheKey = self::CACHE_KEY.'_single_'.$id;

        try {
            $industry = Cache::remember($cacheKey, $ttl, function () use ($id) {
                return CatalogIndustry::query()
                    ->with('meta')
                    ->withCount(['category as categories_count'])
                    ->findOrFail($id);
            });

            $payload = [
                'id' => $industry->id,
                'slug' => $industry->slug,
                'meta' => [
                    'name' => $industry->meta->name ?? null,
                    'status' => isset($industry->meta->status)
                        ? (bool) $industry->meta->status
                        : null,
                ],
                'categories_count' => (int) ($industry->categories_count ?? 0),
            ];

            return response()->json([
                'success' => true,
                'data' => $payload,
                'message' => __('Industry fetched successfully.'),
            ], Response::HTTP_OK)
                ->header('Pragma', 'public')
                ->header('Expires', now()->addSeconds($ttl)->toRfc7231String())
                ->header('X-Cache-TTL', $ttl);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => __('Industry not found.'),
            ], Response::HTTP_NOT_FOUND);
        }
    }
}
