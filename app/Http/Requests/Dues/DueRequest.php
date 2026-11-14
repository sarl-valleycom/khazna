<?php

namespace App\Http\Requests\Dues;

use App\Enums\Direction;
use App\Enums\DueKind;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DueRequest extends FormRequest
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
            'direction' => ['required', Rule::in(Direction::values())],
            'kind' => ['required', Rule::in(DueKind::values())],
            'due_on' => ['required', 'date_format:Y-m-d'],
            'amount_cents' => ['required', 'integer', 'min:1', 'max:99999999999999'],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
