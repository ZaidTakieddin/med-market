<?php

namespace App\Constants;

class OrderStatus
{
    public const PENDING = 'pending';

    public const ACCEPTED = 'accepted';

    public const PREPARED = 'prepared';

    public const SHIPPED = 'shipped';

    public const DELIVERED = 'delivered';

    public const CANCELLED = 'cancelled';

    /**
     * Get all valid order statuses.
     *
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::PENDING,
            self::ACCEPTED,
            self::PREPARED,
            self::SHIPPED,
            self::DELIVERED,
            self::CANCELLED,
        ];
    }

    /**
     * Check if a status is valid.
     */
    public static function isValid(string $status): bool
    {
        return in_array($status, self::all(), true);
    }

    /**
     * Get valid transitions from a given status.
     *
     * @return list<string>
     */
    public static function getValidTransitions(string $fromStatus): array
    {
        return match ($fromStatus) {
            self::PENDING => [self::ACCEPTED, self::CANCELLED],
            self::ACCEPTED => [self::PREPARED, self::CANCELLED],
            self::PREPARED => [self::SHIPPED, self::CANCELLED],
            self::SHIPPED => [self::DELIVERED],
            self::DELIVERED => [],
            self::CANCELLED => [],
            default => [],
        };
    }

    /**
     * Check if a transition is valid.
     */
    public static function canTransition(string $fromStatus, string $toStatus): bool
    {
        return in_array($toStatus, self::getValidTransitions($fromStatus), true);
    }
}
