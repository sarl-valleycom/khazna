<?php

namespace App\Http\Requests\Attendance;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AttendanceSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'integer', Rule::exists('employees', 'id')->whereNull('deleted_at')],
            // Algiers wall-clock values from a datetime-local input.
            'started_at' => ['required', 'date_format:Y-m-d\TH:i'],
            'ended_at' => ['nullable', 'date_format:Y-m-d\TH:i', 'after:started_at'],
            'report' => ['nullable', 'string', 'max:5000'],
            'blockers' => ['nullable', 'string', 'max:5000'],
            'reason' => ['required', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'employee_id.exists' => __('Choose an active employee.'),
            'ended_at.after' => __('The end time must be after the start time.'),
            'reason.required' => __('A reason is required for a manual attendance change.'),
        ];
    }
}
