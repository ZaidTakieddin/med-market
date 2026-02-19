<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $offers = [];
        if ($this->relationLoaded('offers') && $this->offers->isNotEmpty()) {
            $offers = $this->offers->map(function ($offer) {
                return [
                    'id' => $offer->id,
                    'name' => $offer->name,
                    'description' => $offer->description,
                    'offerType' => $offer->offer_type,
                    'discountValue' => $offer->discount_value ? (float) $offer->discount_value : null,
                    'discountType' => $offer->discount_type,
                    'quantityRequired' => $offer->quantity_required,
                    'quantityFree' => $offer->quantity_free,
                    'startDate' => $offer->start_date->toISOString(),
                    'endDate' => $offer->end_date?->toISOString(),
                    'isActive' => $offer->is_active,
                    'isCurrentlyActive' => $offer->isActive(),
                    // Pivot data specific to this product-offer relationship
                    'pivot' => [
                        'quantityRequired' => $offer->pivot->quantity_required ?? null,
                        'quantityFree' => $offer->pivot->quantity_free ?? null,
                        'discountOverride' => $offer->pivot->discount_override ? (float) $offer->pivot->discount_override : null,
                        'discountTypeOverride' => $offer->pivot->discount_type_override ?? null,
                    ],
                ];
            })->toArray();
        }

        // Discount applies to pharmacist price only; show discounted price when discount exists
        $pharmacistPrice = $this->pharmacist_price ? (float) $this->pharmacist_price : null;
        $pharmacistPriceAfterDiscount = ($this->discount && $this->discount_type && $pharmacistPrice !== null)
            ? (float) $this->resource->getFinalPharmacistPrice()
            : null;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'category' => new CategoryResource($this->whenLoaded('category')),
            'manufacturer' => new ManufacturerResource($this->whenLoaded('manufacturer')),
            'sku' => $this->sku,
            'barcode' => $this->barcode,
            'unit' => $this->unit,
            'pharmacistPrice' => $pharmacistPrice,
            'customerPrice' => $this->customer_price ? (float) $this->customer_price : null,
            'discount' => $this->discount ? (float) $this->discount : null,
            'discountType' => $this->discount_type,
            'pharmacistPriceAfterDiscount' => $pharmacistPriceAfterDiscount,
            'stockQuantity' => $this->stock_quantity,
            'minStockLevel' => $this->min_stock_level,
            'isActive' => $this->is_active,
            'supplierCompany' => new CompanyResource($this->whenLoaded('supplierCompany')),
            'offers' => $offers,
            'metadata' => $this->metadata,
            'createdAt' => $this->created_at->toISOString(),
            'updatedAt' => $this->updated_at->toISOString(),
        ];
    }
}

