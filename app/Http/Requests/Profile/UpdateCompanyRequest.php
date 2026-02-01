<?php

namespace App\Http\Requests\Profile;

use App\Http\Requests\BaseFormRequest;

class UpdateCompanyRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization handled in controller
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'address' => ['sometimes', 'nullable', 'string', 'max:500'],
            'latitude' => ['sometimes', 'nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['sometimes', 'nullable', 'numeric', 'between:-180,180'],
            'region_id' => ['sometimes', 'nullable', 'integer', 'exists:regions,id'],
            'address_line' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'tax_id' => ['sometimes', 'nullable', 'string', 'max:100'],
            'registration_number' => ['sometimes', 'nullable', 'string', 'max:100'],
        ];
    }
}

