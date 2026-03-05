<?php

namespace App\Http\Controllers\Api\V1\Location;

use App\Http\Controllers\Controller;
use App\Models\Location\Country;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class CountryController extends Controller
{
    private const CACHE_TTL = 86400;

    public function index(Request $request): JsonResponse
    {
        $onlyAllowed = filter_var($request->query('allowed'), FILTER_VALIDATE_BOOLEAN);
        $cacheKey = $onlyAllowed ? Country::CACHE_KEY_ALLOWED : Country::CACHE_KEY_ALL;
        $countries = Cache::remember($cacheKey, self::CACHE_TTL, function () use ($onlyAllowed) {
            $query = Country::query()->select('id', 'name', 'iso2', 'iso3', 'is_allowed', 'is_default', 'is_state_available')->orderBy('name');
            if ($onlyAllowed) {
                $query->allowed();
            }

            return $query->get();
        });

        return response()->json([
            'success' => true,
            'data' => $countries,
        ], Response::HTTP_OK);
    }
}
