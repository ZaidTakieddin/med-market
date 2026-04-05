<?php

namespace App\Constants;

class SupplierClassification
{
    public const WHOLESALE = 'wholesale';
    public const RETAIL = 'retail';
    public const HYBRID = 'hybrid';

    /**
     * Get all valid supplier classifications.
     */
    public static function all(): array
    {
        return [
            self::WHOLESALE,
            self::RETAIL,
            self::HYBRID,
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
     * Classifications that can offer wholesale pricing.
     */
    public static function wholesaleCapable(): array
    {
        return [
            self::WHOLESALE,
            self::HYBRID,
        ];
    }

    /**
     * Classifications that can offer retail pricing.
     */
    public static function retailCapable(): array
    {
        return [
            self::RETAIL,
            self::HYBRID,
        ];
    }
}
