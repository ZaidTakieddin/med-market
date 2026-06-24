<?php

namespace App\Http\Requests\Drivers;

use App\Http\Requests\BaseFormRequest;

class AssignDriverRegionsRequest extends BaseFormRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'region_ids' => ['required', 'array'],
            'region_ids.*' => ['integer', 'exists:regions,id'],
        ];
    }
}
