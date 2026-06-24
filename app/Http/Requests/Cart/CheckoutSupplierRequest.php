<?php

namespace App\Http\Requests\Cart;

use App\Http\Requests\BaseFormRequest;

class CheckoutSupplierRequest extends BaseFormRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'supplier_id' => ['required', 'integer', 'exists:companies,id'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }
}
