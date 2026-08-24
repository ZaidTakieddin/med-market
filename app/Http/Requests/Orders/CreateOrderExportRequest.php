<?php

namespace App\Http\Requests\Orders;

use App\Constants\OrderStatus;
use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class CreateOrderExportRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization handled in controller via OrderExportPolicy
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'format' => ['required', 'string', Rule::in(['csv', 'json', 'xlsx'])],
            'status' => ['nullable', 'string', Rule::in(OrderStatus::all())],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'company_id' => ['nullable', 'integer', 'exists:companies,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'format.required' => 'Export format is required.',
            'format.in' => 'Export format must be csv or json.',
            'date_to.after_or_equal' => 'The end date must be on or after the start date.',
        ];
    }
}
