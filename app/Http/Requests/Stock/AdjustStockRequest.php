<?php

namespace App\Http\Requests\Stock;

use App\Constants\StockMovementType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdjustStockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization handled in controller
    }

    public function rules(): array
    {
        return [
            'quantity' => ['required', 'integer', 'not_in:0'],
            'type' => ['required', 'string', Rule::in([StockMovementType::ADJUSTMENT, StockMovementType::RETURN])],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'quantity.required' => 'Quantity is required.',
            'quantity.integer' => 'Quantity must be an integer.',
            'quantity.not_in' => 'Quantity cannot be zero. Use positive for increase, negative for decrease.',
            'type.required' => 'Movement type is required.',
            'type.in' => 'Invalid movement type. Must be "adjustment" or "return".',
        ];
    }
}

