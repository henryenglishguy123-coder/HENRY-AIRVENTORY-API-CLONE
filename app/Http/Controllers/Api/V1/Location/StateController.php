<?php

namespace App\Http\Controllers\Api\V1\Location;

use App\Http\Controllers\Controller;
use App\Models\Location\State;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class StateController extends Controller
{
    private const CACHE_TTL = 86400;

    public function index(int $countryId): JsonResponse
    {
        $cacheKey = "public_states_list_country_{$countryId}";
        $states = Cache::remember($cacheKey, self::CACHE_TTL, function () use ($countryId) {
            return State::query()
                ->where('country_id', $countryId)
                ->select('id', 'country_id', 'name', 'country_code', 'iso2')
                ->orderBy('name')
                ->get();
        });

        return response()->json([
            'success' => true,
            'data' => $states,
        ], Response::HTTP_OK, [
            'Cache-Control' => 'public, max-age=3600, s-maxage=3600',
            'Pragma' => 'public',
        ]);
    }
}
