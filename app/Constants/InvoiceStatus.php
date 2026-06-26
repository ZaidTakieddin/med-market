<?php

namespace App\Constants;

class InvoiceStatus
{
    public const GENERATED = 'generated';

    public const SENT = 'sent';

    public const PAID = 'paid';

    public const OVERDUE = 'overdue';

    public const CANCELLED = 'cancelled';

    /**
     * Get all valid invoice statuses.
     *
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::GENERATED,
            self::SENT,
            self::PAID,
            self::OVERDUE,
            self::CANCELLED,
        ];
    }

    /**
     * Check if an invoice status is valid.
     */
    public static function isValid(string $status): bool
    {
        return in_array($status, self::all(), true);
    }
}
