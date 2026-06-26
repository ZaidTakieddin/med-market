<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Offer
 */
class OfferResource extends JsonResource
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
            'description' => $this->description,
            'offerType' => $this->offer_type,
            'discountValue' => $this->discount_value ? (float) $this->discount_value : null,
            'quantityRequired' => $this->quantity_required,
            'quantityFree' => $this->quantity_free,
            'startDate' => $this->start_date->toISOString(),
            'endDate' => $this->end_date?->toISOString(),
            'isActive' => $this->is_active,
            'isCurrentlyActive' => $this->isActive(),
            'supplierCompany' => new CompanyResource($this->whenLoaded('supplierCompany')),
            'products' => $this->whenLoaded('products', function () {
                return $this->products->map(fn ($product) => array_merge(
                    (new ProductResource($product))->toArray(request()),
                    ['quantityRequired' => $product->pivot->quantity_required ?? $this->quantity_required]
                ));
            }),
            'createdAt' => $this->created_at?->toISOString(),
            'updatedAt' => $this->updated_at?->toISOString(),
        ];
    }
}
