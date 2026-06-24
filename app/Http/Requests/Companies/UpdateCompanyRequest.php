<?php

namespace App\Http\Requests\Companies;

use App\Constants\BuyerClassification;
use App\Constants\SupplierClassification;
use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class UpdateCompanyRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'supplier_classification' => ['nullable', 'string', Rule::in(SupplierClassification::all())],
            'buyer_classification' => ['nullable', 'string', Rule::in(BuyerClassification::all())],
            'min_order_amount' => ['nullable', 'numeric', 'min:0'],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:500'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'region_id' => ['nullable', 'integer', 'exists:regions,id'],
            'address_line' => ['nullable', 'string', 'max:1000'],
            'tax_id' => ['nullable', 'string', 'max:100'],
            'registration_number' => ['nullable', 'string', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
