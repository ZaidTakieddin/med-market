<?php

namespace App\Constants;

class PaymentStatus
{
    public const PENDING = 'pending';

    public const PAID = 'paid';

    public const PARTIAL = 'partial';

    public const OVERDUE = 'overdue';

    public const REFUNDED = 'refunded';

    /**
     * Get all valid payment statuses.
     *
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::PENDING,
            self::PAID,
            self::PARTIAL,
            self::OVERDUE,
            self::REFUNDED,
        ];
    }

    /**
     * Check if a payment status is valid.
     */
    public static function isValid(string $status): bool
    {
        return in_array($status, self::all(), true);
    }
}
