<?php

namespace App\Constants;

class UserRole
{
    public const ADMIN = 'admin';
    public const SUPPLIER = 'supplier';
    public const BUYER = 'buyer';
    public const DRIVER = 'driver';

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

