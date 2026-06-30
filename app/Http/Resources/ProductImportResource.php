<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\ProductImport
 */
class ProductImportResource extends JsonResource
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
            'originalFilename' => $this->original_filename,
            'totalRows' => $this->total_rows,
            'createdCount' => $this->created_count,
            'updatedCount' => $this->updated_count,
            'failedCount' => $this->failed_count,
            'supplierCompany' => $this->when(
                $this->relationLoaded('supplierCompany') && $this->supplierCompany,
                fn () => new CompanyResource($this->supplierCompany)
            ),
            'startedAt' => $this->started_at?->toISOString(),
            'completedAt' => $this->completed_at?->toISOString(),
            'createdAt' => $this->created_at?->toISOString(),
            'updatedAt' => $this->updated_at?->toISOString(),
        ];
    }
}
