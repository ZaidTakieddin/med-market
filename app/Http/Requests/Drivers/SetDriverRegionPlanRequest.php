<?php

namespace App\Http\Requests\Drivers;

use App\Constants\DayOfWeek;
use App\Constants\RegionPlanType;
use App\Http\Requests\BaseFormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\Rule;

class SetDriverRegionPlanRequest extends BaseFormRequest
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
            'plan_type' => ['required', Rule::in(RegionPlanType::all())],

            // Fixed plan: one or more regions covered every day.
            'region_ids' => ['required_if:plan_type,'.RegionPlanType::FIXED, 'array', 'min:1'],
            'region_ids.*' => ['integer', 'exists:regions,id'],

            // Daily plan: a map of weekday => region id (one region per weekday).
            'schedule' => ['required_if:plan_type,'.RegionPlanType::DAILY, 'array', 'min:1'],
            'schedule.*' => ['integer', 'exists:regions,id'],
        ];
    }

    /**
     * Reject schedule keys that are not valid weekdays.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->input('plan_type') !== RegionPlanType::DAILY) {
                return;
            }

            $schedule = $this->input('schedule');
            if (! is_array($schedule)) {
                return;
            }

            foreach (array_keys($schedule) as $weekday) {
                if (! is_string($weekday) || ! DayOfWeek::isValid($weekday)) {
                    $validator->errors()->add(
                        "schedule.{$weekday}",
                        'Schedule keys must be valid weekdays: '.implode(', ', DayOfWeek::all()).'.'
                    );
                }
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'plan_type.required' => 'Plan type is required.',
            'plan_type.in' => 'Plan type must be one of: '.implode(', ', RegionPlanType::all()).'.',
            'region_ids.required_if' => 'At least one region is required for a fixed plan.',
            'schedule.required_if' => 'A weekday-to-region schedule is required for a daily plan.',
        ];
    }
}
