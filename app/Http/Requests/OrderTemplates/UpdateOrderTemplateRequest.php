<?php

namespace App\Http\Requests\OrderTemplates;

use App\Http\Requests\BaseFormRequest;

class UpdateOrderTemplateRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization handled via OrderTemplatePolicy
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:150'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['sometimes', 'array', 'min:1'],
            'items.*.product_id' => ['required_with:items', 'integer', 'distinct', 'exists:products,id'],
            'items.*.quantity' => ['required_with:items', 'integer', 'min:1'],
        ];
    }
}
