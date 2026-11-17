<?php

namespace App\Http\Requests\Attendance;

use App\Support\AlgerianDate;
use Carbon\CarbonImmutable;
use Closure;
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
            'started_at' => ['required', 'date_format:Y-m-d\TH:i', $this->notInTheFuture(__('The start time cannot be in the future.'))],
            'ended_at' => ['nullable', 'date_format:Y-m-d\TH:i', 'after:started_at', $this->notInTheFuture(__('The end time cannot be in the future.'))],
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

    /**
     * A session records work that already happened, so neither end of it may lie after "now" in Algiers.
     * A value that does not match the format is left to the date_format rule.
     */
    private function notInTheFuture(string $message): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($message): void {
            if (! is_string($value)) {
                return;
            }

            $moment = CarbonImmutable::createFromFormat('!Y-m-d\TH:i', $value, AlgerianDate::TZ);

            if ($moment instanceof CarbonImmutable && $moment->gt(AlgerianDate::now())) {
                $fail($message);
            }
        };
    }
}
