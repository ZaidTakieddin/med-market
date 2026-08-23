<?php

namespace App\Http\Requests\Orders;

use App\Constants\PaymentTerms;
use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class CreateOrderRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization handled in controller
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'supplier_company_id' => ['required', 'integer', 'exists:companies,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.notes' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'payment_method' => ['nullable', 'string', Rule::in(PaymentTerms::all())],
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
        ];
    }
}
