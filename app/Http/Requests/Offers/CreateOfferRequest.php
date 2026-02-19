<?php

namespace App\Http\Requests\Offers;

use App\Constants\DiscountType;
use App\Constants\OfferType;
use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class CreateOfferRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization handled in controller
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'offer_type' => ['required', 'string', Rule::in(OfferType::all())],
            'discount_value' => ['nullable', 'numeric', 'min:0'],
            'discount_type' => ['nullable', 'string', Rule::in(DiscountType::all())],
            'quantity_required' => ['required', 'integer', 'min:1'],
            'quantity_free' => ['nullable', 'integer', 'min:0'],
            'start_date' => ['required', 'date', 'after_or_equal:today'],
            'end_date' => ['nullable', 'date', 'after:start_date'],
            'is_active' => ['nullable', 'boolean'],
            'product_ids' => ['required', 'array', 'min:1'],
            'product_ids.*' => ['integer', 'exists:products,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Offer name is required.',
            'offer_type.required' => 'Offer type is required.',
            'offer_type.in' => 'Invalid offer type.',
            'start_date.required' => 'Start date is required.',
            'start_date.after_or_equal' => 'Start date must be today or later.',
            'end_date.after' => 'End date must be after start date.',
            'product_ids.required' => 'At least one product must be selected.',
            'product_ids.min' => 'At least one product must be selected.',
        ];
    }
}

