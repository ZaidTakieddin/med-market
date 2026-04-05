<?php

namespace App\Constants;

class PaymentTerms
{
    public const CASH = 'cash';
    public const NET_15 = 'net_15';
    public const NET_30 = 'net_30';
    public const NET_60 = 'net_60';

    /**
     * Get all valid payment terms.
     */
    public static function all(): array
    {
        return [
            self::CASH,
            self::NET_15,
            self::NET_30,
            self::NET_60,
        ];
    }

    /**
     * Check if a payment term is valid.
     */
    public static function isValid(string $term): bool
    {
        return in_array($term, self::all(), true);
    }

    /**
     * Get the number of days for each payment term.
     */
    public static function getDays(string $term): int
    {
        return match ($term) {
            self::CASH => 0,
            self::NET_15 => 15,
            self::NET_30 => 30,
            self::NET_60 => 60,
            default => 0,
        };
    }
}
