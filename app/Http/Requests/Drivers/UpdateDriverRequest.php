<?php

namespace App\Http\Requests\Drivers;

use App\Constants\DriverStatus;
use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class UpdateDriverRequest extends BaseFormRequest
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
        $driverId = $this->route('id');

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'max:255', Rule::unique('users', 'email')->ignore($driverId)],
            'phone' => ['sometimes', 'nullable', 'string', 'max:20'],
            'password' => ['sometimes', 'string', 'min:8'],
            'work_start_time' => ['sometimes', 'nullable', 'regex:/^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/'],
            'work_end_time' => ['sometimes', 'nullable', 'regex:/^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/'],
            'employment_start_date' => ['sometimes', 'nullable', 'date'],
            'employment_end_date' => ['sometimes', 'nullable', 'date', 'after_or_equal:employment_start_date'],
            'driver_status' => ['sometimes', Rule::in(DriverStatus::all())],
        ];
    }

    public function messages(): array
    {
        return [
            'work_start_time.regex' => 'Work start time must be in HH:MM format.',
            'work_end_time.regex' => 'Work end time must be in HH:MM format.',
            'employment_end_date.after_or_equal' => 'Last day at work must be on or after the employment start date.',
            'driver_status.in' => 'Driver status must be one of: '.implode(', ', DriverStatus::all()).'.',
        ];
    }
}
