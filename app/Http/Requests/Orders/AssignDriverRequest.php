<?php

namespace App\Http\Requests\Orders;

use App\Http\Requests\BaseFormRequest;

class AssignDriverRequest extends BaseFormRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'driver_id' => ['required', 'integer', 'exists:users,id'],
        ];
    }
}
