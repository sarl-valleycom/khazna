<?php

namespace App\Http\Requests\Payroll;

use App\Enums\AdjustmentType;
use App\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdjustmentRequest extends FormRequest
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
        $isAdvance = $this->input('type') === AdjustmentType::Advance->value;

        return [
            'employee_id' => ['required', 'integer', Rule::exists('employees', 'id')->whereNull('deleted_at')],
            'type' => ['required', Rule::enum(AdjustmentType::class)],
            'month' => ['required', 'date_format:Y-m'],
            'amount_cents' => ['required', 'integer', 'min:1', 'max:99999999999999'],
            'reason' => ['required', 'string', 'max:255'],
            // Only an advance moves money today. Other types may send these as
            // null (the form does), so they are only checked for an advance.
            'paid_on' => $isAdvance ? ['required', 'date_format:Y-m-d'] : ['prohibited'],
            'method' => $isAdvance ? ['required', Rule::enum(PaymentMethod::class)] : ['prohibited'],
            'account_id' => $isAdvance ? ['required', 'integer'] : ['prohibited'],
            // Needed only when the month's salary has already been paid, in whole or in part.
            'correction_reason' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'amount_cents.required' => __('Enter the amount.'),
            'amount_cents.min' => __('Enter an amount greater than zero.'),
            'reason.required' => __('Enter a reason.'),
            'month.date_format' => __('Choose the month.'),
            'paid_on.required' => __('Choose the day the advance was paid.'),
            'method.required' => __('Choose how it was paid.'),
            'account_id.required' => __('Choose the account it was paid from.'),
            'paid_on.prohibited' => __('Only an advance has a payment date.'),
            'method.prohibited' => __('Only an advance has a payment method.'),
            'account_id.prohibited' => __('Only an advance is paid from an account.'),
        ];
    }
}
