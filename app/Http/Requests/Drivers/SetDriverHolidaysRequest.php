<?php

namespace App\Http\Requests\Drivers;

use App\Constants\DayOfWeek;
use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class SetDriverHolidaysRequest extends BaseFormRequest
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
            'holidays' => ['present', 'array'],
            'holidays.*' => [Rule::in(DayOfWeek::all())],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'holidays.present' => 'The holidays field is required (use an empty array to clear holidays).',
            'holidays.*.in' => 'Each holiday must be a valid weekday: '.implode(', ', DayOfWeek::all()).'.',
        ];
    }
}
