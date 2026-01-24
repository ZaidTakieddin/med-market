<?php

namespace App\Http\Requests\Offers;

use App\Constants\DiscountType;
use App\Constants\OfferType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOfferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization handled in controller
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'offer_type' => ['sometimes', 'string', Rule::in(OfferType::all())],
            'discount_value' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'discount_type' => ['sometimes', 'nullable', 'string', Rule::in(DiscountType::all())],
            'quantity_required' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'quantity_free' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'start_date' => ['sometimes', 'date'],
            'end_date' => ['sometimes', 'date', 'after:start_date'],
            'is_active' => ['sometimes', 'boolean'],
            'product_ids' => ['sometimes', 'array', 'min:1'],
            'product_ids.*' => ['integer', 'exists:products,id'],
        ];
    }
}

