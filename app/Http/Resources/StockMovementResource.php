<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\StockMovement
 */
class StockMovementResource extends JsonResource
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
            'product' => new ProductResource($this->whenLoaded('product')),
            'order' => new OrderResource($this->whenLoaded('order')),
            'user' => new UserResource($this->whenLoaded('user')),
            'type' => $this->type,
            'quantity' => $this->quantity,
            'quantityBefore' => $this->quantity_before,
            'quantityAfter' => $this->quantity_after,
            'reason' => $this->reason,
            'batchNumber' => $this->batch_number,
            'expiryDate' => $this->expiry_date?->toDateString(),
            'createdAt' => $this->created_at?->toISOString(),
        ];
    }
}
