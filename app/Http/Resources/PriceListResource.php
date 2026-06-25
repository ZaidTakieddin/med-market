<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\PriceList
 */
class PriceListResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'supplierCompany' => new CompanyResource($this->whenLoaded('supplierCompany')),
            'buyerCompany' => new CompanyResource($this->whenLoaded('buyerCompany')),
            'product' => new ProductResource($this->whenLoaded('product')),
            'supplierCompanyId' => $this->supplier_company_id,
            'buyerCompanyId' => $this->buyer_company_id,
            'productId' => $this->product_id,
            'customPrice' => (float) $this->custom_price,
            'minQty' => $this->min_qty,
            'validFrom' => $this->valid_from?->toISOString(),
            'validTo' => $this->valid_to?->toISOString(),
            'isActive' => $this->is_active,
            'isCurrentlyValid' => $this->isCurrentlyValid(),
            'notes' => $this->notes,
            'createdAt' => $this->created_at?->toISOString(),
            'updatedAt' => $this->updated_at?->toISOString(),
        ];
    }
}
