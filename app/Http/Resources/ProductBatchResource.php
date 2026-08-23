<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\ProductBatch
 */
class ProductBatchResource extends JsonResource
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
            'batchNumber' => $this->batch_number,
            'expiryDate' => $this->expiry_date?->toDateString(),
            'isExpired' => $this->expiry_date !== null && $this->expiry_date->isPast(),
            'quantity' => $this->quantity,
            'createdAt' => $this->created_at?->toISOString(),
        ];
    }
}
