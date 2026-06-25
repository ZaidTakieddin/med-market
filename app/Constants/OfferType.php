<?php

namespace App\Constants;

class OfferType
{
    public const BUY_X_GET_Y = 'buy_x_get_y';

    public const PERCENTAGE_DISCOUNT = 'percentage_discount';

    public const FIXED_DISCOUNT = 'fixed_discount';

    public const BUNDLE_PERCENTAGE = 'bundle_percentage';

    public const BUNDLE_FIXED = 'bundle_fixed';

    /**
     * Get all offer types.
     *
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::BUY_X_GET_Y,
            self::PERCENTAGE_DISCOUNT,
            self::FIXED_DISCOUNT,
            self::BUNDLE_PERCENTAGE,
            self::BUNDLE_FIXED,
        ];
    }

    /**
     * Check if an offer type is valid.
     */
    public static function isValid(string $type): bool
    {
        return in_array($type, self::all(), true);
    }

    /**
     * Check if offer type uses percentage discount.
     */
    public static function isPercentage(string $type): bool
    {
        return in_array($type, [self::PERCENTAGE_DISCOUNT, self::BUNDLE_PERCENTAGE], true);
    }

    /**
     * Check if offer type uses fixed discount.
     */
    public static function isFixed(string $type): bool
    {
        return in_array($type, [self::FIXED_DISCOUNT, self::BUNDLE_FIXED], true);
    }

    /**
     * Check if offer type is a bundle.
     */
    public static function isBundle(string $type): bool
    {
        return in_array($type, [self::BUNDLE_PERCENTAGE, self::BUNDLE_FIXED], true);
    }
}
