<?php

namespace App\Http\Requests\Employees;

use Illuminate\Foundation\Http\FormRequest;

class SalaryRequest extends FormRequest
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
            'amount_cents' => ['required', 'integer', 'min:1', 'max:99999999999999'],
            'effective_from' => ['required', 'date_format:Y-m-d'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'amount_cents.required' => __('Enter the monthly salary.'),
            'effective_from.required' => __('Choose the date the salary starts.'),
        ];
    }
}
