<?php

namespace App\Constants;

class CompanyType
{
    public const SUPPLIER = 'supplier';
    public const BUYER = 'buyer';

    /**
     * Get all valid company types.
     */
    public static function all(): array
    {
        return [
            self::SUPPLIER,
            self::BUYER,
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

