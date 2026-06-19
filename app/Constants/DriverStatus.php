<?php

namespace App\Constants;

class DriverStatus
{
    public const AVAILABLE = 'available';

    public const ON_DELIVERY = 'on_delivery';

    public const OFF = 'off';

    /**
     * Get all valid driver statuses.
     *
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::AVAILABLE,
            self::ON_DELIVERY,
            self::OFF,
        ];
    }

    /**
     * Check if a status is valid.
     */
    public static function isValid(string $status): bool
    {
        return in_array($status, self::all(), true);
    }
}
