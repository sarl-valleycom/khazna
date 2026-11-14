<?php

namespace App\Http\Requests\Contracts;

use App\Enums\Cycle;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ContractRequest extends FormRequest
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
            'client_id' => [
                'required',
                'integer',
                Rule::exists('clients', 'id')->whereNull('deleted_at'),
            ],
            'amount_cents' => ['required', 'integer', 'min:1', 'max:99999999999999'],
            'cycle' => [
                'required',
                Rule::in([Cycle::Monthly->value, Cycle::Quarterly->value, Cycle::Yearly->value]),
            ],
            'starts_on' => ['required', 'date_format:Y-m-d'],
            'ends_on' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:starts_on'],
            'auto_renew' => ['required', 'boolean'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'office_id' => [
                'nullable',
                'integer',
                Rule::exists('offices', 'id')->whereNull('deleted_at'),
            ],
            'attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:10240'],
        ];
    }
}
