<?php

namespace App\Http\Requests\Users;

use App\Constants\UserRole;
use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends BaseFormRequest
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
        /** @var \App\Models\User|null $target */
        $target = $this->route('user');

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => [
                'sometimes',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($target?->getKey()),
            ],
            'phone' => ['nullable', 'string', 'max:50'],
            'password' => ['nullable', 'string', 'min:8'],
            'role' => ['sometimes', 'string', Rule::in(UserRole::all())],
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
