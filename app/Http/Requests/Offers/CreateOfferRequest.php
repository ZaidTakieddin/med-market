<?php

namespace App\Http\Requests\Offers;

use App\Constants\OfferType;
use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class CreateOfferRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization handled in controller
    }

    /**
     * Accept camelCase JSON (aligned with OfferResource) as well as snake_case.
     */
    protected function prepareForValidation(): void
    {
        $map = [
            'offerType' => 'offer_type',
            'discountValue' => 'discount_value',
            'quantityRequired' => 'quantity_required',
            'quantityFree' => 'quantity_free',
            'startDate' => 'start_date',
            'endDate' => 'end_date',
            'isActive' => 'is_active',
            'productIds' => 'product_ids',
        ];
        $merge = [];
        foreach ($map as $camel => $snake) {
            if ($this->has($camel) && ! $this->has($snake)) {
                $merge[$snake] = $this->input($camel);
            }
        }
        if (! empty($merge)) {
            $this->merge($merge);
        }
        if ($this->input('end_date') === '' || $this->input('endDate') === '') {
            $this->merge(['end_date' => null]);
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'offer_type' => ['required', 'string', Rule::in(OfferType::all())],
            'discount_value' => ['nullable', 'numeric', 'min:0'],
            'quantity_required' => ['required', 'integer', 'min:1'],
            'quantity_free' => ['nullable', 'integer', 'min:0'],
            'start_date' => ['required', 'date', 'after_or_equal:today'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'is_active' => ['nullable', 'boolean'],
            'product_ids' => ['nullable', 'array', 'min:1'],
            'product_ids.*' => ['integer', 'exists:products,id'],
            'products' => ['nullable', 'array', 'min:1'],
            'products.*.product_id' => ['required_with:products', 'integer', 'exists:products,id'],
            'products.*.quantity' => ['required_with:products', 'integer', 'min:1'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $isBundle = in_array($this->input('offer_type'), [OfferType::BUNDLE_PERCENTAGE, OfferType::BUNDLE_FIXED], true);
            $products = $this->input('products', []);
            $productIds = $this->input('product_ids', []);
            $hasProducts = is_array($products) && count($products) >= 1;
            $hasProductIds = is_array($productIds) && count($productIds) >= 1;

            if ($isBundle) {
                if (! $hasProducts) {
                    $validator->errors()->add('products', 'Bundle offers require products array with product_id and quantity for each product.');
                } elseif (count($products) < 2) {
                    $validator->errors()->add('products', 'Bundle offers require at least 2 products.');
                }
            } elseif (! $hasProducts && ! $hasProductIds) {
                $validator->errors()->add('product_ids', 'At least one product must be selected (use product_ids or products).');
            }
        });
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Offer name is required.',
            'offer_type.required' => 'Offer type is required.',
            'offer_type.in' => 'Invalid offer type.',
            'start_date.required' => 'Start date is required.',
            'start_date.after_or_equal' => 'Start date must be today or later.',
            'end_date.after_or_equal' => 'End date must be on or after the start date.',
            'product_ids.required' => 'At least one product must be selected.',
            'product_ids.min' => 'At least one product must be selected.',
            'products.required' => 'Bundle offers require products with quantity for each product.',
            'products.*.product_id.required_with' => 'Each product must have a product_id.',
            'products.*.quantity.required_with' => 'Each product must have a quantity.',
        ];
    }
}
