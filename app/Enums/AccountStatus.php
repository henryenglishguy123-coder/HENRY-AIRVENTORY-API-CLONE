<?php

namespace App\Enums;

use App\Traits\HasMetadata;

enum AccountStatus: int
{
    use HasMetadata;

    case DISABLED = 0;
    case ENABLED = 1;
    case BLOCKED = 2;
    case SUSPENDED = 3;

    public function label(): string
    {
        return match ($this) {
            self::DISABLED => 'Disabled',
            self::ENABLED => 'Enabled',
            self::BLOCKED => 'Blocked',
            self::SUSPENDED => 'Suspended',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::DISABLED => 'secondary',
            self::ENABLED => 'success',
            self::BLOCKED => 'danger',
            self::SUSPENDED => 'warning',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::DISABLED => 'mdi-account-off',
            self::ENABLED => 'mdi-account-check',
            self::BLOCKED => 'mdi-account-remove',
            self::SUSPENDED => 'mdi-account-alert',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::DISABLED => 'Account is deactivated and cannot log in.',
            self::ENABLED => 'Account is active and has full access.',
            self::BLOCKED => 'Account is blocked due to policy violations.',
            self::SUSPENDED => 'Account is temporarily suspended pending review.',
        };
    }

    /**
     * Get the string representation of the account status.
     */
    public function toString(): string
    {
        return match ($this) {
            self::DISABLED => 'disabled',
            self::ENABLED => 'enabled',
            self::BLOCKED => 'blocked',
            self::SUSPENDED => 'suspended',
        };
    }

    /**
     * Get the account status string from an integer value.
     * Defaults to 'enabled' for null or invalid values.
     */
    public static function fromInt(?int $value): string
    {
        if ($value === null) {
            return self::ENABLED->toString();
        }

        $status = self::tryFrom($value);

        return $status?->toString() ?? self::ENABLED->toString();
    }
}
