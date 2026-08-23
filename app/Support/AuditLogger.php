<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class AuditLogger
{
    /**
     * Persist a sensitive-action audit entry. Never throws: auditing must not
     * break the primary operation.
     *
     * @param  array<string, mixed>|null  $properties
     */
    public static function record(?User $user, string $action, ?Model $subject = null, ?array $properties = null): void
    {
        try {
            AuditLog::create([
                'user_id' => $user?->id,
                'action' => $action,
                'subject_type' => $subject !== null ? $subject::class : null,
                'subject_id' => $subject?->getKey(),
                'properties' => $properties,
                'ip_address' => request()->ip(),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
