<?php

namespace App\Http\Requests\Users;

use App\Constants\UserRole;
use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class CreateUserRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization handled via UserPolicy in the controller
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:50'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', 'string', Rule::in(UserRole::all())],
            'company_id' => [
                'nullable',
                'integer',
                'exists:companies,id',
                Rule::requiredIf(in_array($this->input('role'), UserRole::companyRoles(), true)),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'email.unique' => 'A user with this email already exists.',
            'role.in' => 'Invalid role.',
            'company_id.required' => 'A company is required for supplier, buyer, driver, and salesman roles.',
            'password.min' => 'Password must be at least 8 characters.',
        ];
    }
}
