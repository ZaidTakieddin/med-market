<?php

namespace App\Http\Requests\Stock;

use App\Constants\StockMovementType;
use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class BulkStockRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization per product handled via ProductPolicy in the controller
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $movementTypes = [StockMovementType::IN, StockMovementType::OUT, StockMovementType::ADJUSTMENT, StockMovementType::RETURN];

        return [
            'operations' => ['required', 'array', 'min:1', 'max:200'],
            'operations.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'operations.*.type' => ['required', 'string', Rule::in($movementTypes)],
            // Signed for adjustment/return, positive for in/out.
            'operations.*.quantity' => ['required', 'integer', 'not_in:0'],
            'operations.*.reason' => ['nullable', 'string', 'max:500'],
            'operations.*.batch_number' => ['nullable', 'string', 'max:100'],
            'operations.*.expiry_date' => ['nullable', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'operations.required' => 'At least one operation is required.',
            'operations.min' => 'At least one operation is required.',
            'operations.max' => 'A bulk request may contain at most 200 operations.',
            'operations.*.product_id.exists' => 'One or more products do not exist.',
            'operations.*.type.in' => 'Movement type must be one of: '.implode(', ', StockMovementType::all()).'.',
            'operations.*.quantity.not_in' => 'Quantity cannot be zero.',
        ];
    }
}
