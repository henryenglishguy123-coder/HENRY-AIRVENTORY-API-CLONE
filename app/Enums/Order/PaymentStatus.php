<?php

namespace App\Enums\Order;

enum PaymentStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Failed = 'failed';
    case Refunded = 'refunded';

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
