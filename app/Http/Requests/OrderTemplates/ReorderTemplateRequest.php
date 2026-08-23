<?php

namespace App\Http\Requests\OrderTemplates;

use App\Constants\PaymentTerms;
use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class ReorderTemplateRequest extends BaseFormRequest
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
            'payment_method' => ['nullable', 'string', Rule::in(PaymentTerms::all())],
        ];
    }
}
