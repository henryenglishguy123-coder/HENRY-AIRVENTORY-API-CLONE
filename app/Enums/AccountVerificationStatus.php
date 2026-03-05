<?php

namespace App\Enums;

use App\Traits\HasMetadata;

enum AccountVerificationStatus: int
{
    use HasMetadata;

    case REJECTED = 0;
    case VERIFIED = 1;
    case PENDING = 2;
    case HOLD = 3;
    case PROCESSING = 4;

    /**
     * Get the string representation of the account verification status.
     */
    public function toString(): string
    {
        return match ($this) {
            self::REJECTED => 'rejected',
            self::VERIFIED => 'verified',
            self::PENDING => 'pending',
            self::HOLD => 'hold',
            self::PROCESSING => 'processing',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::REJECTED => 'Rejected',
            self::VERIFIED => 'Verified',
            self::PENDING => 'Pending',
            self::HOLD => 'Hold',
            self::PROCESSING => 'Processing',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::REJECTED => 'danger',
            self::VERIFIED => 'success',
            self::PENDING => 'dark',
            self::HOLD => 'secondary',
            self::PROCESSING => 'info',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::REJECTED => 'mdi-shield-remove',
            self::VERIFIED => 'mdi-shield-check',
            self::PENDING => 'mdi-shield-clock',
            self::HOLD => 'mdi-shield-pause',
            self::PROCESSING => 'mdi-shield-sync',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::REJECTED => 'Verification failed. Factory must address issues.',
            self::VERIFIED => 'Factory is fully verified and trusted.',
            self::PENDING => 'Awaiting initial review from administrators.',
            self::HOLD => 'Verification is on hold, additional data requested.',
            self::PROCESSING => 'Currently being audited by the compliance team.',
        };
    }

    /**
     * Get the account verification status string from an integer value.
     * Defaults to 'pending' for null or invalid values.
     */
    public static function fromInt(?int $value): string
    {
        if ($value === null) {
            return self::PENDING->toString();
        }

        $status = self::tryFrom($value);

        return $status?->toString() ?? self::PENDING->toString();
    }
}
