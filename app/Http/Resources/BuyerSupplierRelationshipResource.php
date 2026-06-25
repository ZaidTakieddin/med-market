<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\BuyerSupplierRelationship
 */
class BuyerSupplierRelationshipResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'buyerCompany' => new CompanyResource($this->whenLoaded('buyerCompany')),
            'supplierCompany' => new CompanyResource($this->whenLoaded('supplierCompany')),
            'buyerCompanyId' => $this->buyer_company_id,
            'supplierCompanyId' => $this->supplier_company_id,
            'paymentTerms' => $this->payment_terms,
            'creditLimit' => $this->credit_limit !== null ? (float) $this->credit_limit : null,
            'currentBalance' => (float) $this->current_balance,
            'availableCredit' => $this->credit_limit !== null
                ? max(0, (float) $this->credit_limit - (float) $this->current_balance)
                : null,
            'isApproved' => $this->is_approved,
            'approvedAt' => $this->approved_at?->toISOString(),
            'notes' => $this->notes,
            'createdAt' => $this->created_at?->toISOString(),
            'updatedAt' => $this->updated_at?->toISOString(),
        ];
    }
}
