<?php

namespace App\Constants;

class ExportStatus
{
    public const QUEUED = 'queued';

    public const PROCESSING = 'processing';

    public const COMPLETED = 'completed';

    public const FAILED = 'failed';

    /**
     * Get all valid export statuses.
     *
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::QUEUED,
            self::PROCESSING,
            self::COMPLETED,
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
