<?php

namespace App\Http\Requests\Orders;

use App\Constants\OrderStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOrderStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization handled in controller
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'string', Rule::in(OrderStatus::all())],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'status.required' => 'Status is required.',
            'status.in' => 'Invalid status. Valid statuses: ' . implode(', ', OrderStatus::all()),
        ];
    }
}

