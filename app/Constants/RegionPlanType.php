<?php

namespace App\Constants;

class RegionPlanType
{
    /**
     * The driver covers the same set of regions every working day.
     */
    public const FIXED = 'fixed';

    /**
     * The driver covers a different region per weekday (a weekly schedule).
     */
    public const DAILY = 'daily';

    /**
     * Get all valid region plan types.
     *
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::FIXED,
            self::DAILY,
        ];
    }

    /**
     * Check if a region plan type is valid.
     */
    public static function isValid(string $type): bool
    {
        return in_array($type, self::all(), true);
    }
}
