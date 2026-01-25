<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
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
            'orderNumber' => $this->order_number,
            'status' => $this->status,
            'buyerCompany' => new CompanyResource($this->whenLoaded('buyerCompany')),
            'supplierCompany' => new CompanyResource($this->whenLoaded('supplierCompany')),
            'creator' => new UserResource($this->whenLoaded('creator')),
            'driver' => new UserResource($this->whenLoaded('driver')),
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
            'subtotal' => (float) $this->subtotal,
            'taxAmount' => (float) $this->tax_amount,
            'totalAmount' => (float) $this->total_amount,
            'notes' => $this->notes,
            'acceptedAt' => $this->accepted_at?->toISOString(),
            'preparedAt' => $this->prepared_at?->toISOString(),
            'shippedAt' => $this->shipped_at?->toISOString(),
            'deliveredAt' => $this->delivered_at?->toISOString(),
            'cancelledAt' => $this->cancelled_at?->toISOString(),
            'cancellationReason' => $this->cancellation_reason,
            'createdAt' => $this->created_at->toISOString(),
            'updatedAt' => $this->updated_at->toISOString(),
        ];
    }
}

