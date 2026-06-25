<?php

namespace App\Constants;

class BuyerClassification
{
    public const PHARMACY = 'pharmacy';

    public const SALESMAN = 'salesman';

    /**
     * Get all valid buyer classifications.
     *
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::PHARMACY,
            self::SALESMAN,
        ];
    }

    /**
     * Check if a classification is valid.
     */
    public static function isValid(string $classification): bool
    {
        return in_array($classification, self::all(), true);
    }

    /**
     * Classifications eligible for wholesale pricing.
     *
     * @return list<string>
     */
    public static function wholesaleEligible(): array
    {
        return [
            self::SALESMAN,
        ];
    }
}
