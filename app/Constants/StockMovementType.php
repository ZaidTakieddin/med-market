<?php

namespace App\Constants;

class StockMovementType
{
    public const IN = 'in';

    public const OUT = 'out';

    public const ADJUSTMENT = 'adjustment';

    public const RETURN = 'return';

    /**
     * Get all valid stock movement types.
     */
    public static function all(): array
    {
        return [
            self::IN,
            self::OUT,
            self::ADJUSTMENT,
            self::RETURN,
        ];
    }

    /**
     * Check if a type is valid.
     */
    public static function isValid(string $type): bool
    {
        return in_array($type, self::all(), true);
    }
}
