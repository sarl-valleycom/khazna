<?php

namespace App\Http\Requests\Contracts;

use App\Enums\ContractStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ContractStatusRequest extends FormRequest
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
            'status' => ['required', Rule::in(ContractStatus::values())],
            'reason' => [
                'nullable',
                'string',
                'max:1000',
                Rule::requiredIf(fn (): bool => in_array($this->input('status'), [ContractStatus::Paused->value, ContractStatus::Canceled->value], true)),
            ],
        ];
    }
}
