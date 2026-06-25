<?php

namespace App\Http\Requests\PriceLists;

use App\Http\Requests\BaseFormRequest;

class UpdatePriceListRequest extends BaseFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'custom_price' => ['sometimes', 'numeric', 'min:0.01'],
            'min_qty' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'valid_from' => ['sometimes', 'nullable', 'date'],
            'valid_to' => ['sometimes', 'nullable', 'date', 'after_or_equal:valid_from'],
            'is_active' => ['sometimes', 'boolean'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'custom_price.min' => 'Custom price must be at least 0.01.',
            'valid_to.after_or_equal' => 'End date must be on or after the start date.',
        ];
    }
}
