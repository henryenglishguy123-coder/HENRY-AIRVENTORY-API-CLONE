<?php

namespace App\Http\Controllers\Api\V1\Location;

use App\Http\Controllers\Controller;
use App\Models\Location\Country;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class FactoryCountryController extends Controller
{
    private const CACHE_TTL = 3600;

    public function index(Request $request): JsonResponse
    {
        $cacheKey = $this->cacheKey();
        $data = Cache::remember($cacheKey, self::CACHE_TTL, function () {
            return Country::query()
                ->whereHas('factoryBusinesses.factory', function ($query) {
                    $query->where('account_status', true)
                        ->where('account_verified', true)
                        ->whereExists(function ($sub) {
                            $sub->selectRaw(1)
                                ->from('catalog_product_inventory as cpi')
                                ->whereColumn('cpi.factory_id', 'factory_users.id');
                        });
                })
                ->select(['id', 'name', 'iso3', 'iso2'])
                ->distinct()
                ->orderBy('name')
                ->get()
                ->map(fn ($country) => [
                    'id' => $country->id,
                    'name' => $country->name,
                    'iso3' => $country->iso3,
                    'iso2' => $country->iso2,
                ])
                ->values();
        });

        $etag = $this->generateEtag($data);
        if ($request->headers->get('If-None-Match') === $etag) {
            return response()
                ->json(null, Response::HTTP_NOT_MODIFIED)
                ->setEtag($etag)
                ->setPublic()
                ->setMaxAge(self::CACHE_TTL)
                ->setSharedMaxAge(self::CACHE_TTL);
        }

        return response()
            ->json([
                'success' => true,
                'data' => $data,
            ], Response::HTTP_OK)
            ->setEtag($etag)
            ->setPublic()
            ->setMaxAge(self::CACHE_TTL)
            ->setSharedMaxAge(self::CACHE_TTL);
    }

    private function cacheKey(): string
    {
        return 'factory:countries:active:v1';
    }

    private function generateEtag(mixed $data): string
    {
        return sprintf('"%s"', sha1(json_encode($data)));
    }
}
