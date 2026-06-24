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

    /**
     * Get all valid supplier classifications.
     */
    public static function supplierClassifications(): array
    {
        return [
            SupplierClassification::WHOLESALE,
            SupplierClassification::RETAIL,
            SupplierClassification::HYBRID,
        ];
    }

    /**
     * Get all valid buyer classifications.
     */
    public static function buyerClassifications(): array
    {
        return [
            BuyerClassification::PHARMACY,
            BuyerClassification::SALESMAN,
        ];
    }
}
