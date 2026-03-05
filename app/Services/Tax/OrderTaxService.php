<?php

namespace App\Services\Tax;

use App\Models\Location\State;
use App\Models\Sales\Order\SalesOrder;
use App\Models\Tax\TaxRule;

class OrderTaxService
{
    protected TaxResolverService $taxResolverService;

    public function __construct(TaxResolverService $taxResolverService)
    {
        $this->taxResolverService = $taxResolverService;
    }

    /**
     * Get applicable tax rule for the order.
     */
    public function getApplicableTaxRule(SalesOrder $order): ?TaxRule
    {
        $address = $this->getOrderAddress($order);

        if (! $address) {
            return null;
        }

        $stateCode = $this->getStateCode($address->state_id);

        return $this->taxResolverService->resolve(
            $address->country_id,
            $stateCode,
            $address->zip_code
        );
    }

    /**
     * Calculate tax for the order.
     */
    public function calculateTax(SalesOrder $order): array
    {
        $address = $this->getOrderAddress($order);

        if (! $address) {
            return [
                'tax' => null,
                'rate' => 0,
                'amount' => 0,
            ];
        }

        $stateCode = $this->getStateCode($address->state_id);

        // We use base_subtotal as the basis for tax calculation
        $subtotal = $order->base_subtotal ?? 0;

        return $this->taxResolverService->calculate(
            $subtotal,
            $address->country_id,
            $stateCode,
            $address->zip_code
        );
    }

    /**
     * Get the address to be used for tax calculation (Shipping > Billing).
     *
     * @return mixed
     */
    protected function getOrderAddress(SalesOrder $order)
    {
        // Try to get shipping address first
        $address = $order->addresses()->where('address_type', 'shipping')->first();

        // Fallback to billing address if shipping is not available
        if (! $address) {
            $address = $order->addresses()->where('address_type', 'billing')->first();
        }

        return $address;
    }

    /**
     * Get state code from state ID.
     */
    protected function getStateCode(?int $stateId): ?string
    {
        if (! $stateId) {
            return null;
        }

        $state = State::find($stateId);

        return $state ? $state->iso2 : null;
    }
}
