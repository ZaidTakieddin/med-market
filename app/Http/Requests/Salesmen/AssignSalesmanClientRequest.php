<?php

namespace App\Http\Requests\Salesmen;

use App\Http\Requests\BaseFormRequest;

class AssignSalesmanClientRequest extends BaseFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'pharmacy_company_id' => ['required', 'integer', 'exists:companies,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'pharmacy_company_id.required' => 'Pharmacy company ID is required.',
            'pharmacy_company_id.exists' => 'The selected pharmacy company does not exist.',
        ];
    }
}
