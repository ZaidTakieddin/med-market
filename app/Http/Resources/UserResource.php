<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\User
 */
class UserResource extends JsonResource
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
            'email' => $this->email,
            'phone' => $this->when($this->isDriver() || $this->isSalesman(), $this->phone),
            'role' => $this->role,
            'company' => new CompanyResource($this->whenLoaded('company')),
            ...($this->isDriver() ? [
                'workStartTime' => $this->work_start_time,
                'workEndTime' => $this->work_end_time,
                'employmentStartDate' => $this->employment_start_date?->toDateString(),
                'employmentEndDate' => $this->employment_end_date?->toDateString(),
                'driverStatus' => $this->driver_status,
                'regionPlanType' => $this->region_plan_type,
                'holidays' => $this->holidayWeekdays(),
                'deliveredOrdersCount' => $this->when(
                    $this->delivered_orders_count !== null,
                    fn (): int => (int) $this->delivered_orders_count
                ),
                'totalOrdersCount' => $this->when(
                    $this->total_orders_count !== null,
                    fn (): int => (int) $this->total_orders_count
                ),
            ] : []),
            'createdAt' => $this->created_at?->toISOString(),
            'updatedAt' => $this->updated_at?->toISOString(),
        ];
    }
}
