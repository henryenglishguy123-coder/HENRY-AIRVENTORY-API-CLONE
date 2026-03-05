<?php

namespace App\Services\Currency;

use App\Models\Currency\Currency;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CurrencyConversionService
{
    /**
     * Convert amount from default currency to target currency.
     */
    public function convert(float|int $amount, string $targetCurrencyCode, ?int $precision = 2): float
    {
        $defaultCurrency = Currency::getDefaultCurrencyOrNull();

        // If no default currency set or target is same as default, return original amount
        if (! $defaultCurrency || $defaultCurrency->code === $targetCurrencyCode) {
            return $this->round($amount, $precision);
        }

        // Fetch target currency from allowed currencies cache
        $targetCurrency = Currency::getAllowedCurrencies()
            ->firstWhere('code', $targetCurrencyCode);

        // If currency not found or has invalid rate, log warning and return original
        if (! $targetCurrency || $targetCurrency->rate <= 0) {
            Log::warning("Currency conversion skipped: Target currency {$targetCurrencyCode} not found or has invalid rate.", [
                'amount' => $amount,
                'target_currency' => $targetCurrencyCode,
            ]);

            return $this->round($amount, $precision);
        }

        // Calculate converted amount: Base * Rate
        $convertedAmount = $amount * $targetCurrency->rate;

        return $this->round($convertedAmount, $precision);
    }

    /**
     * Round the amount to specified precision.
     */
    protected function round(float $amount, ?int $precision): float
    {
        if ($precision === null) {
            return $amount;
        }

        return round($amount, $precision);
    }
}
