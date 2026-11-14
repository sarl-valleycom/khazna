<?php

namespace App\Http\Requests\Accounts;

use App\Enums\AccountType;
use App\Models\Account;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AccountRequest extends FormRequest
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
        $account = $this->route('account');
        $creating = ! $account instanceof Account;

        return [
            'office_id' => [$creating ? 'required' : 'prohibited', 'integer', Rule::exists('offices', 'id')],
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('accounts', 'name')
                    ->where('office_id', $creating ? $this->integer('office_id') : $account->office_id)
                    ->ignore($creating ? null : $account->id),
            ],
            'type' => ['required', Rule::enum(AccountType::class)],
            'opening_balance_cents' => ['required', 'integer', 'between:-100000000000000,100000000000000'],
            'opening_date' => ['required', 'date_format:Y-m-d'],
            'active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.unique' => __('This office already has an account with that name.'),
            'office_id.prohibited' => __('An account cannot be moved to another office.'),
            'opening_balance_cents.required' => __('Enter the opening balance, even if it is zero.'),
            'opening_date.required' => __('Choose the date the opening balance was counted.'),
        ];
    }
}
