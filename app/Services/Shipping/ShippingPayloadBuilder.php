<?php

namespace App\Services\Shipping;

use App\Models\Location\Country;
use App\Models\Sales\Order\SalesOrder;
use Exception;

class ShippingPayloadBuilder
{
    /**
     * Builds a standardized Shipping and Factory detail payload.
     * Use this to get consistent `ship_to`, `ship_from`, and `items` structures
     * before mapping them to specific provider formats (like ShipStation or AfterShip).
     *
     * @throws Exception
     */
    public static function build(SalesOrder $order): array
    {
        $factory = $order->factory;
        $orderItems = $order->items;
        $shippingAddress = $order->addresses->where('address_type', 'shipping')->first();

        if (! $factory) {
            throw new Exception('Missing factory details');
        }

        if (! $shippingAddress) {
            throw new Exception('Shipping address not found');
        }

        if ($orderItems->isEmpty()) {
            throw new Exception('Order has no items');
        }

        // Validate factory origin fields
        $factoryBusiness = $factory->business;
        if (! $factoryBusiness) {
            throw new Exception('Factory business information is missing. Cannot determine ship-from origin.');
        }

        $originAddress = trim($factoryBusiness->registered_address ?? '');
        $originCity = trim($factoryBusiness->city ?? '');
        $originPostal = trim($factoryBusiness->postal_code ?? '');

        // Use renamed countryData/stateData relationships to avoid column name shadowing
        $originCountry = $factoryBusiness->countryData?->iso2 ?? $factoryBusiness->country_id ?? '';
        $originState = $factoryBusiness->stateData?->iso2 ?? $factoryBusiness->stateData?->name ?? '';

        if (! $originAddress || ! $originCity || ! $originPostal || ! $originCountry) {
            throw new Exception('Factory origin address is incomplete (missing street, city, postal code, or country).');
        }

        $toAddressLines = \App\Helpers\AddressHelper::splitAddress(
            ($shippingAddress->address_line_1 ?? '').' '.($shippingAddress->address_line_2 ?? ''),
            50,
            3
        );

        $toAddressLines = \App\Helpers\AddressHelper::splitAddress(
            ($shippingAddress->address_line_1 ?? '').' '.($shippingAddress->address_line_2 ?? ''),
            50,
            3
        );

        $shipTo = [
            'name' => \App\Helpers\AddressHelper::truncate(trim($shippingAddress->first_name.' '.$shippingAddress->last_name), 100),
            'company' => \App\Helpers\AddressHelper::truncate($shippingAddress->company ?? '', 100),
            'street1' => $toAddressLines[0],
            'street2' => $toAddressLines[1],
            'street3' => $toAddressLines[2],
            'city' => \App\Helpers\AddressHelper::truncate($shippingAddress->city ?? '', 50),
            'state' => \App\Helpers\AddressHelper::truncate($shippingAddress->stateData?->iso2 ?? $shippingAddress->state ?? '', 50),
            'postal_code' => \App\Helpers\AddressHelper::truncate($shippingAddress->postal_code ?? '', 20),
            'country' => \App\Helpers\AddressHelper::truncate($shippingAddress->countryData?->iso2 ?? $shippingAddress->country ?? '', 2),
            'phone' => \App\Helpers\AddressHelper::truncate($shippingAddress->phone ?? '', 50),
            'email' => \App\Helpers\AddressHelper::truncate($shippingAddress->email ?? '', 100),
        ];

        // Ensure country codes are uppercase/2-char if they came from fallback strings
        if (strlen($shipTo['country']) > 2) {
            $mapper = ['India' => 'IN', 'United Kingdom' => 'GB', 'United States' => 'US'];
            $shipTo['country'] = $mapper[$shipTo['country']] ?? $shipTo['country'];
        }

        $fromAddressLines = \App\Helpers\AddressHelper::splitAddress($originAddress, 50, 3);

        $fromAddressLines = \App\Helpers\AddressHelper::splitAddress($originAddress, 50, 3);

        $shipFrom = [
            'name' => \App\Helpers\AddressHelper::truncate(trim($factory->first_name.' '.($factory->last_name ?? '')), 100),
            'company' => \App\Helpers\AddressHelper::truncate($factoryBusiness->company_name ?? '', 100),
            'street1' => $fromAddressLines[0],
            'street2' => $fromAddressLines[1],
            'street3' => $fromAddressLines[2],
            'city' => \App\Helpers\AddressHelper::truncate($originCity, 50),
            'state' => \App\Helpers\AddressHelper::truncate($originState, 50),
            'postal_code' => \App\Helpers\AddressHelper::truncate($originPostal, 20),
            'country' => \App\Helpers\AddressHelper::truncate($originCountry, 2),
            'phone' => \App\Helpers\AddressHelper::truncate($factory->phone_number ?? '', 50),
            'email' => \App\Helpers\AddressHelper::truncate($factory->email ?? '', 100),
        ];

        $items = [];
        $totalWeightKgs = 0;

        foreach ($orderItems as $item) {
            $qty = max(1, (int) $item->qty);
            $unitWeightKgs = max(0.01, $item->unit_weight ?? 0.01);

            $items[] = [
                'sku' => $item->sku ?? 'UNKNOWN_SKU',
                'name' => $item->product_name ?? 'Product',
                'quantity' => $qty,
                'unit_weight_kg' => round($unitWeightKgs, 4),
                'total_weight_kg' => round($unitWeightKgs * $qty, 4),
                'price' => (float) ($item->row_price ?? $item->factory_price ?? 0),
                'hs_code' => $item->hs_code ?? $item->product?->hs_code ?? null,
            ];

            $totalWeightKgs += ($qty * $unitWeightKgs);
        }

        return [
            'ship_to' => $shipTo,
            'ship_from' => $shipFrom,
            'items' => $items,
            'total_weight_kg' => round($totalWeightKgs, 2),
            'order_number' => $order->order_number,
            'order_date' => optional($order->created_at)->toIso8601String(),
        ];
    }
}
