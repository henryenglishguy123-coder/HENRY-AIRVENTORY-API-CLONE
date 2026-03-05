<?php

namespace App\Http\Controllers\Admin\Settings\Currency;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\CurrencySettingRequest;
use App\Models\Admin\Store\StoreMeta;
use App\Models\Currency\Currency;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class CurrencySettingController extends Controller
{
    public function index()
    {
        $currencies = Currency::query()->orderBy('code')->get();
        $defaultCurrency = Currency::getDefaultCurrencyOrNull();
        $allowedCurrencies = Currency::getAllowedCurrencies();
        $metaValues = StoreMeta::query()->where('type', 'currency')->pluck('value', 'key')->toArray();
        $setting = (object) ['fixer_io_api_status' => $metaValues['fixer_io_api_status'] ?? 0, 'fixer_io_api_key' => $metaValues['fixer_io_api_key'] ?? ''];

        return view('admin.settings.currency.index', compact('currencies', 'defaultCurrency', 'allowedCurrencies', 'setting'));
    }

    public function update(CurrencySettingRequest $request)
    {
        try {
            DB::transaction(function () use ($request) {
                Currency::setDefaultCurrency($request->input('default_currency_id'));
                Currency::setAllowedCurrencies($request->input('allowed_currency_ids', []));

                $fixerStatus = (int) $request->fixer_io_api_status;
                StoreMeta::updateOrCreate(
                    ['key' => 'fixer_io_api_status', 'type' => 'currency'],
                    ['value' => $fixerStatus]
                );
                StoreMeta::updateOrCreate(
                    ['key' => 'fixer_io_api_key', 'type' => 'currency'],
                    ['value' => $fixerStatus ? $request->fixer_io_api_key : null]
                );
            });

            return response()->json([
                'status' => true,
                'message' => __('Currency settings updated successfully.'),
            ], Response::HTTP_OK);
        } catch (\Throwable $e) {
            Log::error('Failed to update currency settings', ['exception' => $e]);

            return response()->json([
                'status' => false,
                'message' => __('Failed to update currency settings.'),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
