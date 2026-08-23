<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\AuditLog
 */
class AuditLogResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'action' => $this->action,
            'user' => $this->whenLoaded('user', fn () => $this->user?->name),
            'subjectType' => $this->subject_type !== null ? class_basename($this->subject_type) : null,
            'subjectId' => $this->subject_id,
            'properties' => $this->properties,
            'ipAddress' => $this->ip_address,
            'createdAt' => $this->created_at?->toISOString(),
        ];
    }
}
