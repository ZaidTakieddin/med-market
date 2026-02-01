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
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'category' => new CategoryResource($this->whenLoaded('category')),
            'manufacturer' => new ManufacturerResource($this->whenLoaded('manufacturer')),
            'sku' => $this->sku,
            'barcode' => $this->barcode,
            'unit' => $this->unit,
            'price' => $this->price ? (float) $this->price : null, // Legacy field
            'pharmacistPrice' => $this->pharmacist_price ? (float) $this->pharmacist_price : null,
            'customerPrice' => $this->customer_price ? (float) $this->customer_price : null,
            'discount' => $this->discount ? (float) $this->discount : null,
            'discountType' => $this->discount_type,
            'stockQuantity' => $this->stock_quantity,
            'minStockLevel' => $this->min_stock_level,
            'isActive' => $this->is_active,
            'supplierCompany' => new CompanyResource($this->whenLoaded('supplierCompany')),
            'metadata' => $this->metadata,
            'createdAt' => $this->created_at->toISOString(),
            'updatedAt' => $this->updated_at->toISOString(),
        ];
    }
}

