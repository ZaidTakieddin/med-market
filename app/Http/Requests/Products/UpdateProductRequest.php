<?php

namespace App\Http\Requests\Products;

use App\Constants\DiscountType;
use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization handled in controller
    }

    public function rules(): array
    {
        $productId = $this->route('product')->id ?? null;

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'manufacturer_id' => ['nullable', 'integer', 'exists:manufacturers,id'],
            'sku' => ['sometimes', 'string', 'max:100', Rule::unique('products', 'sku')->ignore($productId)],
            'barcode' => ['nullable', 'string', 'max:100'],
            'unit' => ['sometimes', 'string', 'max:50', Rule::in(['piece', 'box', 'bottle', 'pack', 'carton'])],
            'price' => ['sometimes', 'numeric', 'min:0'], // Legacy
            'pharmacist_price' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'customer_price' => ['nullable', 'numeric', 'min:0'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'discount_type' => ['nullable', 'string', Rule::in(DiscountType::all())],
            'min_stock_level' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}

