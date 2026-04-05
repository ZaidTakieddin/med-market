<?php

namespace App\Http\Requests\PriceLists;

use App\Http\Requests\BaseFormRequest;

class CreatePriceListRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'buyer_company_id' => ['required', 'integer', 'exists:companies,id'],
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'custom_price' => ['required', 'numeric', 'min:0.01'],
            'min_qty' => ['nullable', 'integer', 'min:1'],
            'valid_from' => ['nullable', 'date'],
            'valid_to' => ['nullable', 'date', 'after_or_equal:valid_from'],
            'is_active' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'buyer_company_id.required' => 'Buyer company is required.',
            'buyer_company_id.exists' => 'The selected buyer company does not exist.',
            'product_id.required' => 'Product is required.',
            'product_id.exists' => 'The selected product does not exist.',
            'custom_price.required' => 'Custom price is required.',
            'custom_price.min' => 'Custom price must be at least 0.01.',
            'valid_to.after_or_equal' => 'End date must be on or after the start date.',
        ];
    }
}
