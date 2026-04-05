<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CartItemResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $baseUnitPrice = (float) $this->unit_price;

        if ($this->applied_offer_id && $this->relationLoaded('product')) {
            $product = $this->product;
            $baseUnitPrice = match ($this->pricing_type) {
                'wholesale' => (float) ($product->getFinalWholesalePrice() ?? $product->getFinalPharmacistPrice()),
                'custom' => (float) $this->unit_price,
                default => (float) $product->getFinalPharmacistPrice(),
            };
        }

        $baseTotalPrice = round($this->quantity * $baseUnitPrice, 2);

        return [
            'id' => $this->id,
            'product' => new ProductResource($this->whenLoaded('product')),
            'quantity' => $this->quantity,
            'unitPrice' => (float) $this->unit_price,
            'totalPrice' => (float) $this->total_price,
            'pricingType' => $this->pricing_type,
            'baseUnitPrice' => $baseUnitPrice,
            'baseTotalPrice' => $baseTotalPrice,
            'appliedOffer' => new OfferResource($this->whenLoaded('appliedOffer')),
            'appliedOfferId' => $this->applied_offer_id,
            'notes' => $this->notes,
        ];
    }
}

