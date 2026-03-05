<?php

namespace App\Http\Controllers\Admin\Settings\Currency;

use App\Http\Controllers\Controller;
use App\Models\Admin\Store\StoreMeta;
use App\Models\Currency\Currency;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class CurrencyRateController extends Controller
{
    public function index(): View
    {
        $defaultCurrency = Currency::getDefaultCurrencyOrNull();
        $allowedCurrencies = Currency::getAllowedCurrencies();

        return view('admin.settings.currency.rates', compact('defaultCurrency', 'allowedCurrencies'));
    }

    public function update(Request $request): JsonResponse
    {
        $defaultCurrency = Currency::getDefaultCurrencyOrNull();
        $allowedCurrencies = Currency::getAllowedCurrencies();

        if (! $defaultCurrency) {
            return response()->json([
                'success' => false,
                'message' => __('Default currency is not configured.'),
            ], Response::HTTP_BAD_REQUEST);
        }

        if ($allowedCurrencies->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => __('No allowed currencies configured.'),
            ], Response::HTTP_BAD_REQUEST);
        }

        $metaValues = StoreMeta::query()
            ->where('type', 'currency')
            ->pluck('value', 'key')
            ->toArray();

        $fixerEnabled = (int) ($metaValues['fixer_io_api_status'] ?? 0) === 1;
        $apiKey = $metaValues['fixer_io_api_key'] ?? null;

        if (! $fixerEnabled) {
            return response()->json([
                'success' => false,
                'message' => __('Fixer API is disabled. Enable it in currency settings first.'),
            ], Response::HTTP_BAD_REQUEST);
        }

        if (empty($apiKey)) {
            return response()->json([
                'success' => false,
                'message' => __('Fixer API key is not configured.'),
            ], Response::HTTP_BAD_REQUEST);
        }

        $codes = $allowedCurrencies
            ->pluck('code')
            ->push($defaultCurrency->code)
            ->unique()
            ->values()
            ->all();

        try {
            $response = Http::get(config('services.fixer.base_url'), [
                'access_key' => $apiKey,
                'symbols' => implode(',', $codes),
            ]);

            if (! $response->ok()) {
                return response()->json([
                    'success' => false,
                    'message' => __('Failed to fetch currency rates from Fixer API.'),
                ], Response::HTTP_SERVICE_UNAVAILABLE);
            }

            $data = $response->json();

            if (! ($data['success'] ?? false)) {
                $errorMessage = $data['error']['info'] ?? __('Fixer API returned an error.');

                return response()->json([
                    'success' => false,
                    'message' => $errorMessage,
                ], Response::HTTP_SERVICE_UNAVAILABLE);
            }

            $apiRates = $data['rates'] ?? [];

            if (empty($apiRates)) {
                return response()->json([
                    'success' => false,
                    'message' => __('Invalid response from Fixer API.'),
                ], Response::HTTP_BAD_GATEWAY);
            }

            if (! isset($apiRates[$defaultCurrency->code])) {
                return response()->json([
                    'success' => false,
                    'message' => __('Default currency is not available in Fixer API response.'),
                ], Response::HTTP_BAD_GATEWAY);
            }

            $defaultRateAgainstBase = (float) $apiRates[$defaultCurrency->code];

            if ($defaultRateAgainstBase <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => __('Invalid default currency rate from Fixer API.'),
                ], Response::HTTP_BAD_GATEWAY);
            }
            $calculatedRates = [];
            $calculatedRates[$defaultCurrency->id] = 1.0;
            foreach ($allowedCurrencies as $currency) {
                if ($currency->id === $defaultCurrency->id) {
                    continue;
                }
                $code = $currency->code;
                if (! isset($apiRates[$code])) {
                    $rateRelativeDefault = (float) $currency->rate;
                } else {
                    $rateAgainstBase = (float) $apiRates[$code];
                    $rateRelativeDefault = $rateAgainstBase / $defaultRateAgainstBase;
                }
                $rateRelativeDefault = round($rateRelativeDefault, 4);
                $calculatedRates[$currency->id] = $rateRelativeDefault;
            }

            foreach ($calculatedRates as $currencyId => $rate) {
                Currency::query()
                    ->whereKey($currencyId)
                    ->update(['rate' => $rate]);
            }

            Currency::clearCache();

            return response()->json([
                'success' => true,
                'message' => __('Currency rates updated from Fixer API successfully.'),
                'rates' => $calculatedRates,
            ]);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => __('Unexpected error while updating currency rates.'),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function saveManual(Request $request): RedirectResponse
    {
        $defaultCurrency = Currency::getDefaultCurrencyOrNull();
        $allowedCurrencies = Currency::getAllowedCurrencies();

        if (! $defaultCurrency) {
            return redirect()
                ->back()
                ->withErrors(__('Default currency is not configured.'));
        }

        if ($allowedCurrencies->isEmpty()) {
            return redirect()
                ->back()
                ->withErrors(__('No allowed currencies configured.'));
        }

        $validated = $request->validate([
            'currency_rates' => ['required', 'array'],
            'currency_rates.*' => ['required', 'numeric', 'gt:0'],
        ]);

        $rates = $validated['currency_rates'];
        $updates = [];

        foreach ($allowedCurrencies as $currency) {
            $id = $currency->id;

            if ($id === $defaultCurrency->id) {
                $updates[] = [
                    'id' => $id,
                    'rate' => 1.0,
                ];

                continue;
            }

            if (! array_key_exists($id, $rates)) {
                $updates[] = [
                    'id' => $id,
                    'rate' => $currency->rate,
                ];

                continue;
            }

            $rate = (float) $rates[$id];
            $rate = round($rate, 4);

            $updates[] = [
                'id' => $id,
                'rate' => $rate,
            ];
        }

        if (! empty($updates)) {
            foreach ($updates as $row) {
                Currency::query()
                    ->whereKey($row['id'])
                    ->update(['rate' => $row['rate']]);
            }
        }

        Currency::clearCache();

        return redirect()
            ->route('admin.settings.currency.rates')
            ->with('success', __('Currency rates updated successfully.'));
    }
}
