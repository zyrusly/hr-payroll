<?php

namespace App\Modules\Location\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateScheduleRequest extends FormRequest
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
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'week_number' => ['required', 'integer', 'min:1', 'max:53'],
            's_monday' => ['nullable', 'date_format:H:i'],
            's_tuesday' => ['nullable', 'date_format:H:i'],
            's_wednesday' => ['nullable', 'date_format:H:i'],
            's_thursday' => ['nullable', 'date_format:H:i'],
            's_friday' => ['nullable', 'date_format:H:i'],
            's_saturday' => ['nullable', 'date_format:H:i'],
            's_sunday' => ['nullable', 'date_format:H:i'],
            'schedule_notes' => ['nullable', 'string', 'max:255'],
        ];
    }
}
