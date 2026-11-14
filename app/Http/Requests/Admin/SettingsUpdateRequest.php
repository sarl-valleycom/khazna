<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SettingsUpdateRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'week_start' => ['required', Rule::in(['saturday', 'sunday', 'monday'])],
            'reminder_template' => ['required', 'string', 'max:1000'],
            'currency_label' => ['required', 'string', 'max:10'],
            'salary_due_day' => ['required', 'integer', 'min:1', 'max:28'],
            // How many days after delivery a milestone falls due. Optional so older callers keep working.
            'milestone_payment_term_days' => ['sometimes', 'integer', 'min:0', 'max:365'],
        ];
    }
}
