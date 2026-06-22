<?php

namespace App\Constants;

class DayOfWeek
{
    public const SATURDAY = 'saturday';

    public const SUNDAY = 'sunday';

    public const MONDAY = 'monday';

    public const TUESDAY = 'tuesday';

    public const WEDNESDAY = 'wednesday';

    public const THURSDAY = 'thursday';

    public const FRIDAY = 'friday';

    /**
     * Get all weekdays, ordered Saturday-first to match the local work week.
     *
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::SATURDAY,
            self::SUNDAY,
            self::MONDAY,
            self::TUESDAY,
            self::WEDNESDAY,
            self::THURSDAY,
            self::FRIDAY,
        ];
    }

    /**
     * Check if a weekday is valid.
     */
    public static function isValid(string $day): bool
    {
        return in_array($day, self::all(), true);
    }
}
