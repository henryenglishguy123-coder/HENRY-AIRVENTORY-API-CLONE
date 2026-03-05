<?php

namespace App\Http\Controllers\Api\V1\Config;

use App\Http\Controllers\Controller;
use App\Models\Admin\Store\Store;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class PanelConfigController extends Controller
{
    private const CACHE_TTL = 3600;

    public function index(): JsonResponse
    {
        $store = Cache::remember(Store::PANEL_CONFIG_CACHE_KEY, self::CACHE_TTL, fn () => Store::query()->with('meta')->first());
        if (! $store) {
            return $this->noCacheResponse(['success' => false, 'message' => __('Store not found.')], Response::HTTP_NOT_FOUND);
        }
        $meta = $this->buildMetaPayload($store);
        if (empty($meta)) {
            return $this->noCacheResponse(['success' => false, 'message' => __('Store meta not found.')], Response::HTTP_NOT_FOUND);
        }
        $storePayload = $this->buildStorePayload($store, $meta);

        return $this->noCacheResponse(['success' => true, 'message' => __('Panel config fetched successfully.'), 'data' => $storePayload], Response::HTTP_OK);
    }

    protected function noCacheResponse(array $payload, int $status): JsonResponse
    {
        return response()->json($payload, $status)->header('Cache-Control', 'no-cache, no-store, must-revalidate')->header('Pragma', 'no-cache')->header('Expires', '0');
    }

    private function buildMetaPayload(Store $store): array
    {
        if ($store->meta->isEmpty()) {
            return [];
        }
        $meta = $store->meta->pluck('value', 'key')->toArray();
        $meta['vendor_login_page_image'] = $this->formatSingleImage($meta['vendor_login_page_image'] ?? null);
        $meta['factory_login_page_images'] = $this->formatImageCollection($meta['factory_login_page_images'] ?? null);

        return $meta;
    }

    private function buildStorePayload(Store $store, array $meta): array
    {
        $storeArray = $store->toArray();
        $storeArray['icon'] = $this->formatSingleImage($store->icon);
        $storeArray['favicon'] = $this->formatSingleImage($store->favicon);
        $storeArray['meta'] = $meta;

        return $storeArray;
    }

    private function formatSingleImage(?string $image): ?string
    {
        return $image ? getImageUrl($image) : null;
    }

    private function formatImageCollection(string|array|null $images): array
    {
        if (is_string($images)) {
            $decoded = json_decode($images, true);
            $images = is_array($decoded) ? $decoded : [];
        }
        if (! is_array($images)) {
            $images = [];
        }

        return collect($images)->filter()->map(fn (string $image) => getImageUrl($image))->values()->all();
    }
}
