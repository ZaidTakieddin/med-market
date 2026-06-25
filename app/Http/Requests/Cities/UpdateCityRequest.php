<?php

namespace App\Http\Requests\Cities;

use App\Http\Requests\BaseFormRequest;
use App\Models\City;
use Illuminate\Validation\Rule;

class UpdateCityRequest extends BaseFormRequest
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
        $city = $this->route('city');
        $cityId = $city instanceof City ? $city->id : $this->route('id');

        return [
            'name' => ['sometimes', 'string', 'max:255', Rule::unique('cities', 'name')->ignore($cityId)],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('cities', 'slug')->ignore($cityId)],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
