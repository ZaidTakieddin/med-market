<?php

namespace App\Http\Requests\Salesmen;

use App\Http\Requests\BaseFormRequest;

class AssignSalesmanRegionsRequest extends BaseFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'region_ids' => ['required', 'array', 'min:1'],
            'region_ids.*' => ['required', 'integer', 'exists:regions,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'region_ids.required' => 'At least one region ID is required.',
            'region_ids.min' => 'At least one region ID is required.',
            'region_ids.*.exists' => 'One or more region IDs do not exist.',
        ];
    }
}
