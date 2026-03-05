<?php

namespace App\Enums\Shipping;

enum ShipmentStatusEnum: string
{
    case PENDING = 'pending';
    case CREATING = 'creating';
    case PROCESSING = 'processing';
    case COMPLETED = 'completed';
    case FAILED = 'failed';
    case CANCELLED = 'cancelled';
    case DELIVERED = 'delivered';
    case IN_TRANSIT = 'in_transit';
    case RETURNED = 'returned';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::CREATING => 'Creating',
            self::PROCESSING => 'Processing',
            self::COMPLETED => 'Completed',
            self::FAILED => 'Failed',
            self::CANCELLED => 'Cancelled',
            self::DELIVERED => 'Delivered',
            self::IN_TRANSIT => 'In Transit',
            self::RETURNED => 'Returned',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PENDING => 'secondary',
            self::CREATING => 'info',
            self::PROCESSING => 'info',
            self::COMPLETED => 'success',
            self::FAILED => 'danger',
            self::CANCELLED => 'warning',
            self::DELIVERED => 'success',
            self::IN_TRANSIT => 'primary',
            self::RETURNED => 'warning',
        };
    }

    public static function activeStates(): array
    {
        return [
            self::PENDING,
            self::CREATING,
            self::PROCESSING,
            self::IN_TRANSIT,
        ];
    }

    public static function completedStates(): array
    {
        return [
            self::COMPLETED,
            self::DELIVERED,
            self::RETURNED,
        ];
    }

    public static function errorStates(): array
    {
        return [
            self::FAILED,
            self::CANCELLED,
        ];
    }
}
