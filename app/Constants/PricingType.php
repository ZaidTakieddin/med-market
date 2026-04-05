<?php

namespace App\Constants;

class PricingType
{
    public const WHOLESALE = 'wholesale';
    public const RETAIL = 'retail';
    public const CUSTOM = 'custom';

    /**
     * Get all valid pricing types.
     */
    public static function all(): array
    {
        return [
            self::WHOLESALE,
            self::RETAIL,
            self::CUSTOM,
        ];
    }

    /**
     * Check if a pricing type is valid.
     */
    public static function isValid(string $type): bool
    {
        return in_array($type, self::all(), true);
    }
}
