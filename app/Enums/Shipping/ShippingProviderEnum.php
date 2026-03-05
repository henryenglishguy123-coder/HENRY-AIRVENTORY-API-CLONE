<?php

namespace App\Enums\Shipping;

enum ShippingProviderEnum: string
{
    case AfterShip = 'aftership';
    case ShipStation = 'shipstation';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function options(): array
    {
        return array_map(fn ($case) => [
            'label' => ucwords(str_replace('_', ' ', $case->value)),
            'value' => $case->value,
        ], self::cases());
    }
}
