<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\SalesmanClient
 */
class SalesmanClientResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'salesmanUser' => new UserResource($this->whenLoaded('salesmanUser')),
            'salesmanCompany' => new CompanyResource($this->whenLoaded('salesmanCompany')),
            'pharmacyCompany' => new CompanyResource($this->whenLoaded('pharmacyCompany')),
            'salesmanUserId' => $this->salesman_user_id,
            'salesmanCompanyId' => $this->salesman_company_id,
            'pharmacyCompanyId' => $this->pharmacy_company_id,
            'isActive' => $this->is_active,
            'assignedAt' => $this->assigned_at?->toISOString(),
            'createdAt' => $this->created_at?->toISOString(),
            'updatedAt' => $this->updated_at?->toISOString(),
        ];
    }
}
