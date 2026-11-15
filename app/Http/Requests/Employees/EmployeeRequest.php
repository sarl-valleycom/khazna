<?php

namespace App\Http\Requests\Employees;

use App\Enums\EmployeeStatus;
use App\Enums\EmploymentType;
use App\Models\Employee;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EmployeeRequest extends FormRequest
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
        // Someone without the salary permission cannot even send a salary: it is refused, not ignored.
        $mayTouchSalary = $this->user()?->can('view-salaries') ?? false;
        $salary = $mayTouchSalary ? ['nullable'] : ['prohibited'];
        $employee = $this->route('employee');

        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'title' => ['nullable', 'string', 'max:255'],
            'hire_date' => ['nullable', 'date_format:Y-m-d'],
            'office_id' => ['required', 'integer', Rule::exists('offices', 'id')->whereNull('deleted_at')],
            'status' => ['sometimes', Rule::enum(EmployeeStatus::class)],
            // Discord IDs are 64-bit integers: keep them as strings everywhere.
            'discord_user_id' => [
                'nullable',
                'string',
                'regex:/^\d{15,32}$/',
                Rule::unique('employees', 'discord_user_id')->ignore($employee instanceof Employee ? $employee->id : null),
            ],
            'contract_type' => ['sometimes', Rule::enum(EmploymentType::class)],
            'hourly_rate' => [...($mayTouchSalary ? ['nullable'] : ['prohibited']), 'integer', 'min:0', 'max:99999999999999'],
            'expected_minutes_per_day' => ['sometimes', 'integer', 'min:0', 'max:1440'],
            'work_days' => ['sometimes', 'array', 'max:7'],
            'work_days.*' => ['integer', 'between:0,6', 'distinct'],
            'schedule_start' => ['nullable', 'date_format:H:i', 'required_with:schedule_end'],
            'schedule_end' => ['nullable', 'date_format:H:i', 'required_with:schedule_start'],
            'late_grace_minutes' => ['sometimes', 'integer', 'min:0', 'max:240'],
            'reminders_enabled' => ['sometimes', 'boolean'],
            // "amount from a date" for a new employee; later raises go through the salary route.
            'salary_cents' => [...$salary, 'integer', 'min:1', 'max:99999999999999'],
            'salary_from' => [...$salary, 'date_format:Y-m-d', 'required_with:salary_cents'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => __('Name is required.'),
            'office_id.required' => __('Choose the office this person works in.'),
            'salary_cents.prohibited' => __('You are not allowed to set salaries.'),
            'salary_from.prohibited' => __('You are not allowed to set salaries.'),
            'hourly_rate.prohibited' => __('You are not allowed to set salaries.'),
            'salary_from.required_with' => __('Choose the date the salary starts.'),
            'discord_user_id.regex' => __('Paste the numeric Discord user ID (15 to 32 digits).'),
            'discord_user_id.unique' => __('That Discord account is already linked to another employee.'),
            'schedule_end.required_with' => __('Set both the start and the end of the schedule.'),
            'schedule_start.required_with' => __('Set both the start and the end of the schedule.'),
        ];
    }
}
