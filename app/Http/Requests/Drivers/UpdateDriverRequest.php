<?php

namespace App\Http\Requests\Drivers;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class UpdateDriverRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization handled in controller
    }

    public function rules(): array
    {
        $driverId = $this->route('id');

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'max:255', Rule::unique('users', 'email')->ignore($driverId)],
            'password' => ['sometimes', 'string', 'min:8'],
        ];
    }
}

