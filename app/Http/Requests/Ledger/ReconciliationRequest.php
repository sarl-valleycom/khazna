<?php

namespace App\Http\Requests\Ledger;

use Illuminate\Foundation\Http\FormRequest;

class ReconciliationRequest extends FormRequest
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
            'account_id' => ['required', 'integer'],
            'as_of' => ['required', 'date_format:Y-m-d'],
            // A count can be negative (an overdraft), so only a whole number is required.
            'counted_balance_cents' => ['required', 'integer', 'between:-100000000000000,100000000000000'],
            'note' => ['nullable', 'string', 'max:500'],
            'record_difference' => ['sometimes', 'boolean'],
            'reason' => ['nullable', 'string', 'max:255', 'required_if_accepted:record_difference'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'account_id.required' => __('Choose the account.'),
            'as_of.required' => __('Choose the date of the count.'),
            'counted_balance_cents.required' => __('Enter the balance you counted or read.'),
            'reason.required_if_accepted' => __('Enter a reason for the adjustment.'),
        ];
    }
}
