<?php

namespace App\Http\Requests\BuyerSupplierRelationships;

use App\Constants\PaymentTerms;
use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class UpdateBuyerSupplierRelationshipRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'payment_terms' => ['sometimes', 'string', Rule::in(PaymentTerms::all())],
            'credit_limit' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'is_approved' => ['sometimes', 'boolean'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }
}
