<?php

namespace App\Constants;

class ProductImportStatus
{
    public const PENDING = 'pending';

    public const PROCESSING = 'processing';

    public const COMPLETED = 'completed';

    public const COMPLETED_WITH_ERRORS = 'completed_with_errors';

    public const FAILED = 'failed';

    /**
     * Get all valid product import statuses.
     *
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::PENDING,
            self::PROCESSING,
            self::COMPLETED,
            self::COMPLETED_WITH_ERRORS,
            self::FAILED,
        ];
    }

    /**
     * Check if a status is valid.
     */
    public static function isValid(string $status): bool
    {
        return in_array($status, self::all(), true);
    }
}
