<?php

namespace App\Http\Requests\Orders;

use Illuminate\Foundation\Http\FormRequest;

class CreateOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization handled in controller
    }

    public function rules(): array
    {
        return [
            'supplier_company_id' => ['required', 'integer', 'exists:companies,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.notes' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'supplier_company_id.required' => 'Supplier company is required.',
            'supplier_company_id.exists' => 'The selected supplier company does not exist.',
            'items.required' => 'Order must have at least one item.',
            'items.min' => 'Order must have at least one item.',
            'items.*.product_id.exists' => 'One or more products do not exist.',
            'items.*.quantity.min' => 'Quantity must be at least 1.',
            'items.*.unit_price.min' => 'Unit price cannot be negative.',
        ];
    }
}

