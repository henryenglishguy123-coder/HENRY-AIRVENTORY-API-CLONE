<?php

namespace App\Services\Tax;

use App\Models\Location\Country;
use App\Models\Tax\TaxRule;

class TaxResolverService
{
    /**
     * Resolve applicable tax rule for given address
     */
    public function resolve(
        int $countryId,
        ?string $stateCode = null,
        ?string $postalCode = null
    ): ?TaxRule {
        return TaxRule::query()
            ->active()
            ->with(['tax', 'zone'])
            ->whereHas('zone', function ($query) use ($countryId, $stateCode, $postalCode) {

                // Match country
                $query->where('country_id', $countryId);

                // Match state (if provided)
                $query->where(function ($q) use ($stateCode) {
                    $q->whereNull('state_code')
                        ->orWhere('state_code', $stateCode);
                });

                // Match postal code range (if provided)
                if ($postalCode) {
                    $query->where(function ($q) use ($postalCode) {
                        $q->whereNull('postal_code_start')
                            ->orWhere(function ($q2) use ($postalCode) {
                                $q2->where('postal_code_start', '<=', $postalCode)
                                    ->where('postal_code_end', '>=', $postalCode);
                            });
                    });
                }
            })
            ->orderByDesc('priority')   // higher priority first
            ->orderByDesc('id')         // deterministic fallback
            ->first();
    }

    /**
     * Calculate tax amount for subtotal
     */
    public function calculate(
        float $subtotal,
        int $countryId,
        ?string $stateCode = null,
        ?string $postalCode = null
    ): array {
        $rule = $this->resolve($countryId, $stateCode, $postalCode);

        if (! $rule) {
            return [
                'tax' => null,
                'rate' => 0,
                'amount' => 0,
            ];
        }

        $amount = round(($subtotal * $rule->rate) / 100, 2);

        return [
            'tax' => $rule->tax->code,
            'rate' => $rule->rate,
            'amount' => $amount,
        ];
    }
}
