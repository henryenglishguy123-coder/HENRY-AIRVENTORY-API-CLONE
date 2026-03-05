<?php

namespace App\Http\Controllers\Api\V1\Settings\Currency;

use App\Http\Controllers\Controller;
use App\Models\Currency\Currency;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CurrencyController extends Controller
{
    /**
     * Show default currency and allowed currencies
     */
    public function index(Request $request): JsonResponse
    {
        $default = Currency::getDefaultCurrencyOrNull();
        $allowed = Currency::getAllowedCurrencies();

        return response()->json([
            'default_currency' => $default ? [
                'id' => $default->id,
                'currency' => $default->currency,
                'code' => $default->code,
                'symbol' => $default->symbol,
                'localization_code' => $default->localization_code,
                'rate' => $default->rate,
                'is_default' => $default->is_default,
                'is_allowed' => $default->is_allowed,
            ] : null,
            'allowed_currencies' => $allowed->map(function (Currency $c) use ($default) {
                return [
                    'id' => $c->id,
                    'currency' => $c->currency,
                    'code' => $c->code,
                    'symbol' => $c->symbol,
                    'localization_code' => $c->localization_code,
                    'rate' => $c->rate,
                    'is_default' => $default ? $c->id === $default->id : false,
                    'is_allowed' => $c->is_allowed,
                ];
            }),
        ]);
    }
}
