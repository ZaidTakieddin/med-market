<?php

namespace App\Http\Requests\BuyerSupplierRelationships;

use App\Constants\PaymentTerms;
use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class CreateBuyerSupplierRelationshipRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'buyer_company_id' => ['required', 'integer', 'exists:companies,id'],
            'supplier_company_id' => ['required', 'integer', 'exists:companies,id', 'different:buyer_company_id'],
            'payment_terms' => ['required', 'string', Rule::in(PaymentTerms::all())],
            'credit_limit' => ['nullable', 'numeric', 'min:0'],
            'is_approved' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'buyer_company_id.required' => 'Buyer company is required.',
            'buyer_company_id.exists' => 'The selected buyer company does not exist.',
            'supplier_company_id.required' => 'Supplier company is required.',
            'supplier_company_id.exists' => 'The selected supplier company does not exist.',
            'supplier_company_id.different' => 'Supplier and buyer companies must be different.',
            'payment_terms.required' => 'Payment terms are required.',
            'payment_terms.in' => 'Invalid payment terms. Valid options: ' . implode(', ', PaymentTerms::all()),
        ];
    }
}
