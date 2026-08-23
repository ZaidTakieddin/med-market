<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\OrderTemplate
 */
class OrderTemplateResource extends JsonResource
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
            'name' => $this->name,
            'notes' => $this->notes,
            'itemsCount' => $this->whenCounted('items'),
            'createdBy' => new UserResource($this->whenLoaded('creator')),
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item): array => [
                'productId' => $item->product_id,
                'quantity' => $item->quantity,
                'product' => $item->relationLoaded('product') && $item->product !== null
                    ? ['id' => (int) $item->product->id, 'name' => (string) $item->product->name, 'sku' => (string) $item->product->sku]
                    : null,
            ])),
            'createdAt' => $this->created_at?->toISOString(),
        ];
    }
}
