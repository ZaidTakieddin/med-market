<?php

namespace App\Http\Requests\Regions;

use App\Http\Requests\BaseFormRequest;
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
        $regionId = $this->route('region')?->id ?? $this->route('id');
        $cityId = $this->input('city_id');

        return [
            'city_id' => ['sometimes', 'integer', 'exists:cities,id'],
            'name' => ['sometimes', 'string', 'max:255', Rule::unique('regions')->where('city_id', $cityId ?? $this->route('region')?->city_id)->ignore($regionId)],
            'slug' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
