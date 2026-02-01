<?php

namespace App\Http\Requests\Cities;

use App\Http\Requests\BaseFormRequest;

class CreateCityRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization handled in controller
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', 'unique:cities,name'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:cities,slug'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'City name is required.',
            'name.unique' => 'This city name already exists.',
            'slug.unique' => 'This slug already exists.',
        ];
    }
}
