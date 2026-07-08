<?php

namespace App\Modules\Schedules\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'employee_ids' => ['required', 'array', 'min:1'],
            'employee_ids.*' => ['required', 'integer', 'exists:employees,id'],
            'default_branch_id' => ['required', 'integer', 'exists:branches,id'],
            'default_start_time' => ['required', 'date_format:H:i'],
            'default_end_time' => ['required', 'date_format:H:i', 'after:default_start_time'],
            'weeks' => ['required', 'array', 'min:1'],
            'weeks.*.year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'weeks.*.week_number' => ['required', 'integer', 'min:1', 'max:53'],
            'weeks.*.days' => ['required', 'array', 'required_array_keys:monday,tuesday,wednesday,thursday,friday,saturday,sunday'],
            'weeks.*.days.*.branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'weeks.*.days.*.start_time' => ['nullable', 'date_format:H:i'],
            'weeks.*.days.*.end_time' => ['nullable', 'date_format:H:i'],
            'weeks.*.days.*.status' => ['required', 'string', 'in:working,rest_day,day_off,leave,holiday'],
            'weeks.*.days.*.notes' => ['nullable', 'string', 'max:255'],
            'weeks.*.schedule_notes' => ['nullable', 'string', 'max:255'],
        ];
    }
}
