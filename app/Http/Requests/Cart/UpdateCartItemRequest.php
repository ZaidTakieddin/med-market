<?php

namespace App\Http\Requests\Cart;

use App\Constants\PricingType;
use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class UpdateCartItemRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization handled in controller
    }

    public function rules(): array
    {
        return [
            'quantity' => ['sometimes', 'integer', 'min:1'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:500'],
            'pricing_type' => ['sometimes', 'string', Rule::in(PricingType::all())],
        ];
    }

    public function messages(): array
    {
        return [
            'quantity.min' => 'Quantity must be at least 1.',
        ];
    }
}
