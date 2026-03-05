<?php

use App\Models\Currency\Currency;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

if (! function_exists('format_price')) {
    function format_price(float|int|null $amount, ?string $currencyCode = null): string
    {
        if ($amount === null || ! is_numeric($amount)) {
            return __('N/A');
        }
        $baseAmount = (float) $amount;
        $baseCurrency = Currency::getDefaultCurrency();
        if (! $baseCurrency || empty($baseCurrency->code)) {
            return number_format($baseAmount, 2);
        }
        $currency = $baseCurrency;
        $convertedAmount = $baseAmount;
        if ($currencyCode && $currencyCode !== $baseCurrency->code) {
            $foundCurrency = Cache::remember("currency_code_{$currencyCode}", now()->addHours(6), fn () => Currency::where('code', $currencyCode)->where('is_allowed', 1)->first());
            if ($foundCurrency && $foundCurrency->rate > 0) {
                $currency = $foundCurrency;
                $convertedAmount = $baseAmount * $foundCurrency->rate;
            } else {
                Log::warning('Currency conversion skipped', [
                    'requested_currency' => $currencyCode,
                    'rate' => $foundCurrency->rate ?? null,
                ]);
            }
        }
        $code = $currency->code ?? 'USD';
        $symbol = $currency->symbol ?? '$';
        $locale = $currency->localization_code ?? config('app.locale', 'en_IN');
        if (class_exists(\NumberFormatter::class)) {
            try {
                $formatter = new \NumberFormatter($locale, \NumberFormatter::CURRENCY);
                $formatted = $formatter->formatCurrency($convertedAmount, $code);
                if ($formatted !== false) {
                    return $formatted;
                }
            } catch (\Throwable $e) {
            }
        }

        return $symbol.number_format($convertedAmount, 2);
    }
}
