<?php

namespace App\Http\Requests\Attendance;

use Illuminate\Foundation\Http\FormRequest;

class AttendanceSettingsRequest extends FormRequest
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
            'auto_close_time' => ['required', 'date_format:H:i'],
            'paid_break_minutes' => ['required', 'integer', 'min:0', 'max:1440'],
            'self_correction_enabled' => ['required', 'boolean'],
            'self_correction_hours' => ['required', 'integer', 'min:1', 'max:168'],
        ];
    }
}
