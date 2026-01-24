<?php

namespace App\Constants;

class OfferType
{
    public const BUY_X_GET_Y = 'buy_x_get_y';
    public const PERCENTAGE_DISCOUNT = 'percentage_discount';
    public const FIXED_DISCOUNT = 'fixed_discount';
    public const BUNDLE = 'bundle';

    /**
     * Get all offer types.
     */
    public static function all(): array
    {
        return [
            self::BUY_X_GET_Y,
            self::PERCENTAGE_DISCOUNT,
            self::FIXED_DISCOUNT,
            self::BUNDLE,
        ];
    }

    /**
     * Check if an offer type is valid.
     */
    public static function isValid(string $type): bool
    {
        return in_array($type, self::all(), true);
    }
}

