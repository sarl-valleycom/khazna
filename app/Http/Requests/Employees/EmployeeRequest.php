<?php

namespace App\Http\Requests\Employees;

use App\Enums\EmployeeStatus;
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

        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'title' => ['nullable', 'string', 'max:255'],
            'hire_date' => ['nullable', 'date_format:Y-m-d'],
            'office_id' => ['required', 'integer', Rule::exists('offices', 'id')->whereNull('deleted_at')],
            'status' => ['sometimes', Rule::enum(EmployeeStatus::class)],
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
            'salary_from.required_with' => __('Choose the date the salary starts.'),
        ];
    }
}
