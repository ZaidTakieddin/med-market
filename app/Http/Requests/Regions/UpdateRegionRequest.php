<?php

namespace App\Http\Requests\Regions;

use App\Http\Requests\BaseFormRequest;
use App\Models\Region;
use Illuminate\Validation\Rule;

class UpdateRegionRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization handled in controller
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $routeRegion = $this->route('region');
        $regionId = $routeRegion instanceof Region ? $routeRegion->id : $this->route('id');
        $cityId = $this->input('city_id') ?? ($routeRegion instanceof Region ? $routeRegion->city_id : null);

        return [
            'city_id' => ['sometimes', 'integer', 'exists:cities,id'],
            'name' => ['sometimes', 'string', 'max:255', Rule::unique('regions')->where('city_id', $cityId)->ignore($regionId)],
            'slug' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
