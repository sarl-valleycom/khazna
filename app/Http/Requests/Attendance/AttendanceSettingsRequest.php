<?php

namespace App\Http\Requests\Attendance;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'digest_time' => ['required', 'date_format:H:i'],
            'reminders_enabled' => ['required', 'boolean'],
            'reminder_after_minutes' => ['required', 'integer', 'min:0', 'max:1440'],
            'away_limit_minutes' => ['required', 'integer', 'min:1', 'max:1440'],
            'paid_break_minutes' => ['required', 'integer', 'min:0', 'max:1440'],
            'self_correction_enabled' => ['required', 'boolean'],
            'self_correction_hours' => ['required', 'integer', 'min:1', 'max:168'],
            'over_10h_reminder_enabled' => ['required', 'boolean'],
            'owner_digest_channel_id' => ['nullable', 'string', 'regex:/^\d{15,32}$/'],
            'reports_channel_id' => ['nullable', 'string', 'regex:/^\d{15,32}$/'],
            'offices' => ['sometimes', 'array'],
            'offices.*.id' => ['required', 'integer', Rule::exists('offices', 'id')->whereNull('deleted_at')],
            'offices.*.attendance_channel_id' => ['nullable', 'string', 'regex:/^\d{15,32}$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'owner_digest_channel_id.regex' => __('Paste the numeric Discord channel ID (15 to 32 digits).'),
            'reports_channel_id.regex' => __('Paste the numeric Discord channel ID (15 to 32 digits).'),
            'offices.*.attendance_channel_id.regex' => __('Paste the numeric Discord channel ID (15 to 32 digits).'),
        ];
    }
}
