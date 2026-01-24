<?php

namespace App\Constants;

class DiscountType
{
    public const PERCENTAGE = 'percentage';
    public const FIXED = 'fixed';

    /**
     * Get all discount types.
     */
    public static function all(): array
    {
        return [
            self::PERCENTAGE,
            self::FIXED,
        ];
    }

    /**
     * Check if a discount type is valid.
     */
    public static function isValid(string $type): bool
    {
        return in_array($type, self::all(), true);
    }
}

