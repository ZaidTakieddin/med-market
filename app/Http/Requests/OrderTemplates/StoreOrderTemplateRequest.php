<?php

namespace App\Http\Requests\OrderTemplates;

use App\Http\Requests\BaseFormRequest;

class StoreOrderTemplateRequest extends BaseFormRequest
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
            'name' => ['required', 'string', 'max:150'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'distinct', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ];
    }
}
