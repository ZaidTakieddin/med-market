<?php

namespace App\Http\Requests\Companies;

use App\Constants\BuyerClassification;
use App\Constants\CompanyType;
use App\Constants\SupplierClassification;
use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class CreateCompanyRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'type' => ['required', 'string', Rule::in(CompanyType::all())],
            'supplier_classification' => ['nullable', 'string', Rule::in(SupplierClassification::all()), 'required_if:type,supplier'],
            'buyer_classification' => ['nullable', 'string', Rule::in(BuyerClassification::all()), 'required_if:type,buyer'],
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

    public function messages(): array
    {
        return [
            'name.required' => 'Company name is required.',
            'type.required' => 'Company type is required.',
            'type.in' => 'Invalid company type. Must be supplier or buyer.',
            'supplier_classification.required_if' => 'Supplier classification is required for supplier companies.',
            'supplier_classification.in' => 'Invalid supplier classification. Must be wholesale, retail, or hybrid.',
            'buyer_classification.required_if' => 'Buyer classification is required for buyer companies.',
            'buyer_classification.in' => 'Invalid buyer classification. Must be pharmacy or salesman.',
        ];
    }
}
