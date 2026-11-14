<?php

namespace App\Http\Requests\Ledger;

use Illuminate\Foundation\Http\FormRequest;

class TransferRequest extends FormRequest
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
            'from_account_id' => ['required', 'integer'],
            'to_account_id' => ['required', 'integer', 'different:from_account_id'],
            'amount_cents' => ['required', 'integer', 'min:1', 'max:99999999999999'],
            'fee_cents' => ['nullable', 'integer', 'min:0', 'max:99999999999999'],
            'transfer_date' => ['required', 'date_format:Y-m-d'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'from_account_id.required' => __('Choose the account the money leaves.'),
            'to_account_id.required' => __('Choose the account the money goes into.'),
            'to_account_id.different' => __('Choose two different accounts.'),
            'amount_cents.required' => __('Enter the amount.'),
            'amount_cents.min' => __('Enter an amount greater than zero.'),
            'transfer_date.required' => __('Choose the transfer date.'),
        ];
    }
}
