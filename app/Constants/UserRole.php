<?php

namespace App\Constants;

class UserRole
{
    public const ADMIN = 'admin';
    public const SUPPLIER = 'supplier';
    public const BUYER = 'buyer';
    public const DRIVER = 'driver';
    public const SALESMAN = 'salesman';

    /**
     * Get all valid roles.
     */
    public static function all(): array
    {
        return [
            self::ADMIN,
            self::SUPPLIER,
            self::BUYER,
            self::DRIVER,
            self::SALESMAN,
        ];
    }

    /**
     * Roles that can be associated with a supplier company.
     */
    public static function supplierRoles(): array
    {
        return [
            self::SUPPLIER,
            self::DRIVER,
            self::SALESMAN,
        ];
    }

    /**
     * Roles that can place orders (buy products).
     */
    public static function buyerRoles(): array
    {
        return [
            self::BUYER,
            self::SALESMAN,
        ];
    }

    /**
     * Check if a role is valid.
     */
    public static function isValid(string $role): bool
    {
        return in_array($role, self::all(), true);
    }
}

