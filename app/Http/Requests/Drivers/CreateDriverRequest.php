<?php

namespace App\Http\Requests\Drivers;

use App\Constants\DriverStatus;
use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class CreateDriverRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization handled in controller
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:20'],
            'password' => ['required', 'string', 'min:8'],
            'work_start_time' => ['nullable', 'regex:/^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/'],
            'work_end_time' => ['nullable', 'regex:/^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/'],
            'employment_start_date' => ['nullable', 'date'],
            'employment_end_date' => ['nullable', 'date', 'after_or_equal:employment_start_date'],
            'driver_status' => ['nullable', Rule::in(DriverStatus::all())],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Driver name is required.',
            'email.required' => 'Email is required.',
            'email.email' => 'Email must be a valid email address.',
            'email.unique' => 'This email is already taken.',
            'password.required' => 'Password is required.',
            'password.min' => 'Password must be at least 8 characters.',
            'phone.max' => 'Phone number must not exceed 20 characters.',
            'work_start_time.regex' => 'Work start time must be in HH:MM format.',
            'work_end_time.regex' => 'Work end time must be in HH:MM format.',
            'employment_end_date.after_or_equal' => 'Last day at work must be on or after the employment start date.',
            'driver_status.in' => 'Driver status must be one of: '.implode(', ', DriverStatus::all()).'.',
        ];
    }
}
