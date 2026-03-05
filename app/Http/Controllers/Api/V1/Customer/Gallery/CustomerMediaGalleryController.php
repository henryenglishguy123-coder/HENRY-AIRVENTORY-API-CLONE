<?php

namespace App\Http\Controllers\Api\V1\Customer\Gallery;

use App\Http\Controllers\Controller;
use App\Models\Customer\Gallery\VendorMediaGallery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class CustomerMediaGalleryController extends Controller
{
    private const CACHE_TTL = 600;

    private const VERSION_TTL = 86400;

    private const DEFAULT_LIMIT = 20;

    private const MAX_LIMIT = 100;

    public function index(Request $request): JsonResponse
    {
        $customer = Auth::guard('customer')->user();
        if (! $customer) {
            return response()->json([
                'success' => false,
                'message' => __('Unauthenticated customer.'),
            ], Response::HTTP_UNAUTHORIZED);
        }

        $validated = $request->validate([
            'limit' => 'nullable|integer|min:1|max:'.self::MAX_LIMIT,
            'page' => 'nullable|integer|min:1',
            'sort' => 'nullable|in:asc,desc',
            'search' => 'nullable|string|max:255',
        ]);

        $limit = (int) ($validated['limit'] ?? self::DEFAULT_LIMIT);
        $page = (int) ($validated['page'] ?? 1);
        $sort = $validated['sort'] ?? 'desc';
        $search = trim((string) ($validated['search'] ?? ''));

        $versionKey = "customer_media_gallery:version:{$customer->id}";
        $version = Cache::remember($versionKey, self::VERSION_TTL, fn () => 1);
        $cacheKey = sprintf('customer_media_gallery:list:v%d:%d:%s:%s:%d:%d', $version, $customer->id, md5($search), $sort, $page, $limit);
        $payload = Cache::remember($cacheKey, self::CACHE_TTL, function () use ($customer, $search, $limit, $sort, $page) {
            $query = VendorMediaGallery::query()
                ->where('vendor_id', $customer->id)
                ->select(['id', 'image_path', 'original_name', 'extension', 'created_at']);
            if ($search !== '') {
                $query->where('original_name', 'like', "%{$search}%");
            }
            $paginator = $query->orderBy('created_at', $sort)->paginate($limit, ['*'], 'page', $page);

            return [
                'items' => $paginator->getCollection()->map(fn ($item) => [
                    'id' => $item->id,
                    'image_url' => getImageUrl($item->image_path),
                    'original_name' => $item->original_name,
                    'extension' => $item->extension,
                    'created_at' => $item->created_at,
                ])->values(),
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'last_page' => $paginator->lastPage(),
                ],
            ];
        });

        return response()
            ->json([
                'success' => true,
                'message' => __('Media gallery fetched successfully.'),
                'data' => $payload,
            ])
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    }

    protected function bumpCacheVersion(int $customerId): void
    {
        $key = "customer_media_gallery:version:{$customerId}";
        Cache::add($key, 1, self::VERSION_TTL);
        Cache::increment($key);
    }
}
