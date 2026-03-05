<?php

namespace App\Http\Controllers\Api\V1\Store;

use App\Http\Controllers\Controller;
use App\Models\StoreChannels\StoreChannel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class StoreChannelController extends Controller
{
    private const CACHE_TTL = 3600;

    public function index(Request $request): JsonResponse
    {
        $cacheKey = $this->cacheKey();
        $payload = Cache::remember($cacheKey, self::CACHE_TTL, function () {
            $channels = StoreChannel::active()->orderBy('name')->get();

            return [
                'data' => $channels->map(fn (StoreChannel $channel) => $channel->toApiArray())->values(),
                'last_modified' => optional($channels->max('updated_at'))->timestamp,
            ];
        });
        $etag = $this->generateEtag($payload['data']);
        if ($request->headers->get('If-None-Match') === $etag) {
            return response()
                ->json(null, 304)
                ->setEtag($etag)
                ->setPublic()
                ->setMaxAge(self::CACHE_TTL)
                ->setSharedMaxAge(self::CACHE_TTL);
        }

        return response()
            ->json([
                'success' => true,
                'data' => $payload['data'],
            ])
            ->setEtag($etag)
            ->setLastModified(
                $payload['last_modified']
                    ? now()->setTimestamp($payload['last_modified'])
                    : null
            )
            ->setPublic()
            ->setMaxAge(self::CACHE_TTL)
            ->setSharedMaxAge(self::CACHE_TTL);
    }

    private function cacheKey(): string
    {
        return 'store_channels:active:v1';
    }

    private function generateEtag(mixed $data): string
    {
        return sprintf('"%s"', sha1(json_encode($data)));
    }
}
