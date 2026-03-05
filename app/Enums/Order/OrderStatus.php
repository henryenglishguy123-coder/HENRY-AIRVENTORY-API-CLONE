<?php

namespace App\Enums\Order;

enum OrderStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Processing = 'processing';
    case ReadyToShip = 'ready_to_ship';
    case Shipped = 'shipped';
    case InTransit = 'in_transit';
    case OutForDelivery = 'out_for_delivery';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';
    case Failed = 'failed';

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
