<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompanyResource extends JsonResource
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
            'type' => $this->type,
            'email' => $this->email,
            'phone' => $this->phone,
            'address' => $this->address,
            'latitude' => $this->latitude ? (float) $this->latitude : null,
            'longitude' => $this->longitude ? (float) $this->longitude : null,
            'regionId' => $this->region_id,
            'region' => $this->when(
                $this->relationLoaded('region'),
                fn() => new RegionResource($this->region)
            ),
            'addressLine' => $this->address_line,
            'taxId' => $this->tax_id,
            'registrationNumber' => $this->registration_number,
            'isActive' => $this->is_active,
            'categories' => $this->relationLoaded('categories') 
                ? CategoryResource::collection($this->categories) 
                : [],
            'manufacturers' => $this->relationLoaded('manufacturers') 
                ? ManufacturerResource::collection($this->manufacturers) 
                : [],
            'createdAt' => $this->created_at->toISOString(),
            'updatedAt' => $this->updated_at->toISOString(),
        ];
    }
}

