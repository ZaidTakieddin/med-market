<?php

namespace App\Http\Requests\Stock;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class TransferStockRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization handled via ProductPolicy::transfer (admin only)
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'to_product_id' => [
                'required',
                'integer',
                Rule::exists('products', 'id'),
            ],
            'quantity' => ['required', 'integer', 'min:1'],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'to_product_id.required' => 'Destination product is required.',
            'to_product_id.exists' => 'The destination product does not exist.',
            'quantity.min' => 'Transfer quantity must be at least 1.',
        ];
    }
}
