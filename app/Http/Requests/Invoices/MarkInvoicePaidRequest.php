<?php

namespace App\Http\Requests\Invoices;

use App\Http\Requests\BaseFormRequest;

class MarkInvoicePaidRequest extends BaseFormRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'amount' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
