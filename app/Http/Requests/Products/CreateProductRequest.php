<?php

namespace App\Http\Requests\Products;

use App\Constants\DiscountType;
use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class CreateProductRequest extends BaseFormRequest
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
            'category' => ['nullable', 'string', 'max:100'],
            'sku' => ['required', 'string', 'max:100', 'unique:products,sku'],
            'barcode' => ['nullable', 'string', 'max:100'],
            'unit' => ['nullable', 'string', 'max:50', Rule::in(['piece', 'box', 'bottle', 'pack', 'carton'])],
            'price' => ['nullable', 'numeric', 'min:0'], // Legacy - kept for backward compatibility
            'pharmacist_price' => ['required_without:price', 'nullable', 'numeric', 'min:0'],
            'customer_price' => ['nullable', 'numeric', 'min:0'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'discount_type' => ['nullable', 'string', Rule::in(DiscountType::all())],
            'stock_quantity' => ['nullable', 'integer', 'min:0'],
            'min_stock_level' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
            'metadata' => ['nullable', 'array'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Product name is required.',
            'sku.required' => 'SKU is required.',
            'sku.unique' => 'This SKU already exists.',
            'price.min' => 'Price cannot be negative.',
            'pharmacist_price.required_without' => 'Either price or pharmacist_price is required.',
            'pharmacist_price.min' => 'Pharmacist price cannot be negative.',
            'customer_price.min' => 'Customer price cannot be negative.',
            'discount.min' => 'Discount cannot be negative.',
            'discount_type.in' => 'Discount type must be either percentage or fixed.',
        ];
    }
}

