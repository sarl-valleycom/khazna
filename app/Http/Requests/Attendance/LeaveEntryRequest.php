<?php

namespace App\Http\Requests\Attendance;

use App\Enums\EmployeeStatus;
use App\Enums\LeaveType;
use App\Models\LeaveRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LeaveEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', LeaveRequest::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'integer', Rule::exists('employees', 'id')->where('status', EmployeeStatus::Active->value)->whereNull('deleted_at')],
            'type' => ['required', Rule::enum(LeaveType::class)],
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'employee_id.required' => __('Choose an employee.'),
            'from.required' => __('Choose the first day of the leave.'),
            'to.required' => __('Choose the last day of the leave.'),
            'to.after_or_equal' => __('The last day cannot be before the first day.'),
        ];
    }
}
