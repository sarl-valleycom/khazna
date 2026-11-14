<?php

namespace App\Http\Requests\Clients;

use App\Enums\Role;
use App\Rules\ValidAlgerianPhone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ClientRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'contact' => ['nullable', 'string', 'max:255'],
            'phone' => ['required', 'string', new ValidAlgerianPhone],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'account_manager_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where('role', Role::AccountManager->value),
            ],
            'office_id' => [
                'nullable',
                'integer',
                Rule::exists('offices', 'id')->whereNull('deleted_at'),
            ],
        ];
    }
}
