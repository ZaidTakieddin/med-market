<?php

namespace App\Http\Requests\Salesmen;

use App\Constants\PaymentTerms;
use App\Constants\PricingType;
use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class PlaceOrderOnBehalfRequest extends BaseFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'buyer_company_id' => ['required', 'integer', 'exists:companies,id'],
            'supplier_company_id' => ['required', 'integer', 'exists:companies,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.pricing_type' => ['nullable', 'string', Rule::in(PricingType::all())],
            'items.*.notes' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'payment_method' => ['nullable', 'string', Rule::in(PaymentTerms::all())],
        ];
    }

    public function messages(): array
    {
        return [
            'buyer_company_id.required' => 'Buyer company is required for on-behalf orders.',
            'buyer_company_id.exists' => 'The selected buyer company does not exist.',
            'supplier_company_id.required' => 'Supplier company is required.',
            'supplier_company_id.exists' => 'The selected supplier company does not exist.',
            'items.required' => 'Order must have at least one item.',
            'items.min' => 'Order must have at least one item.',
        ];
    }
}
