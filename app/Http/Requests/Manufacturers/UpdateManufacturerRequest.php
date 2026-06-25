<?php

namespace App\Http\Requests\Manufacturers;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class UpdateManufacturerRequest extends BaseFormRequest
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
        $manufacturerId = $this->route('manufacturer')->id ?? null;

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('manufacturers', 'name')->ignore($manufacturerId)],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('manufacturers', 'slug')->ignore($manufacturerId)],
            'description' => ['nullable', 'string', 'max:2000'],
            'country' => ['nullable', 'string', 'max:100'],
            'website' => ['nullable', 'url', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Manufacturer name is required.',
            'name.unique' => 'This manufacturer name already exists.',
            'slug.unique' => 'This slug already exists.',
            'website.url' => 'Website must be a valid URL.',
            'email.email' => 'Email must be a valid email address.',
        ];
    }
}
