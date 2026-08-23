<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\OrderExport
 */
class OrderExportResource extends JsonResource
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
            'status' => $this->status,
            'format' => $this->format,
            'filters' => $this->filters,
            'ordersCount' => $this->orders_count,
            'error' => $this->error,
            'company' => new CompanyResource($this->whenLoaded('company')),
            'requestedBy' => new UserResource($this->whenLoaded('user')),
            'createdAt' => $this->created_at?->toIso8601String(),
            'startedAt' => $this->started_at?->toIso8601String(),
            'completedAt' => $this->completed_at?->toIso8601String(),
        ];
    }
}
