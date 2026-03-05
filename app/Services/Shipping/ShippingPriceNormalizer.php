<?php

namespace App\Services\Shipping;

use Illuminate\Support\Facades\Log;

class ShippingPriceNormalizer
{
    /**
     * Minimum price required by ShipStation to avoid API errors.
     */
    private const SHIPSTATION_MIN_PRICE = 1.01;

    /**
     * Minimum weight in grams required by ShipStation.
     */
    private const SHIPSTATION_MIN_WEIGHT_GRAMS = 1;

    /**
     * Normalize item price based on provider requirements.
     */
    public static function normalizeItemPrice(float $price, string $provider): float
    {
        if (strtolower($provider) === 'shipstation') {
            if ($price < self::SHIPSTATION_MIN_PRICE) {
                // Return the minimum acceptable price
                return self::SHIPSTATION_MIN_PRICE;
            }
        }

        return $price;
    }

    /**
     * Normalize weight based on provider requirements.
     */
    public static function normalizeWeight(float $weight, string $unit, string $provider): float
    {
        if (strtolower($provider) === 'shipstation') {
            // ShipStation usually expects grams for small parcels or items
            if (strtolower($unit) === 'gram' || strtolower($unit) === 'g') {
                return max(self::SHIPSTATION_MIN_WEIGHT_GRAMS, $weight);
            }

            // If unit is KG, convert to grams for the check
            if (strtolower($unit) === 'kg' || strtolower($unit) === 'kilogram') {
                $weightInGrams = $weight * 1000;
                if ($weightInGrams < self::SHIPSTATION_MIN_WEIGHT_GRAMS) {
                    return self::SHIPSTATION_MIN_WEIGHT_GRAMS / 1000;
                }
            }
        }

        return $weight;
    }

    /**
     * Log a normalization event with context.
     * Use sparingly to avoid log bloat.
     */
    public static function logNormalization(string $sku, float $original, float $normalized, string $type, string $provider): void
    {
        Log::info("Shipping Normalization: Adjusted {$type} for SKU '{$sku}' from {$original} to {$normalized} for {$provider}.");
    }
}
