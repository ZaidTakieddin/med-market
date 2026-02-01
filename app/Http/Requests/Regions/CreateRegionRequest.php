<?php

namespace App\Http\Requests\Regions;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class CreateRegionRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization handled in controller
    }

    public function rules(): array
    {
        return [
            'city_id' => ['required', 'integer', 'exists:cities,id'],
            'name' => ['required', 'string', 'max:255', Rule::unique('regions')->where('city_id', $this->city_id)],
            'slug' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'city_id.required' => 'City is required.',
            'city_id.exists' => 'Selected city does not exist.',
            'name.required' => 'Region name is required.',
            'name.unique' => 'This region name already exists in the selected city.',
        ];
    }
}
