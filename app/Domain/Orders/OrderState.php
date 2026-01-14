<?php

namespace App\Domain\Orders;

class OrderState
{
    public const PENDING = 'pending';
    public const ACCEPTED = 'accepted';
    public const PREPARED = 'prepared';
    public const SHIPPED = 'shipped';
    public const DELIVERED = 'delivered';
    public const CANCELLED = 'cancelled';

    /**
     * Get all valid states.
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
     * Check if a state is valid.
     */
    public static function isValid(string $state): bool
    {
        return in_array($state, self::all(), true);
    }

    /**
     * Get valid next states for a given state.
     */
    public static function getValidNextStates(string $currentState): array
    {
        return match ($currentState) {
            self::PENDING => [self::ACCEPTED, self::CANCELLED],
            self::ACCEPTED => [self::PREPARED, self::CANCELLED],
            self::PREPARED => [self::SHIPPED, self::CANCELLED],
            self::SHIPPED => [self::DELIVERED],
            self::DELIVERED => [], // Final state
            self::CANCELLED => [], // Final state
            default => [],
        };
    }

    /**
     * Check if a state transition is valid.
     */
    public static function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::getValidNextStates($from), true);
    }
}

