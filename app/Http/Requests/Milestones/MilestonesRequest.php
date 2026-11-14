<?php

namespace App\Http\Requests\Milestones;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Rebalance (and preview) the unpaid milestones of a project. */
class MilestonesRequest extends FormRequest
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
            'milestones' => ['required', 'array', 'min:1', 'max:50'],
            'milestones.*.id' => ['required', 'integer'],
            'milestones.*.label' => ['required', 'string', 'max:255'],
            'milestones.*.mode' => ['required', Rule::in(['percent', 'amount'])],
            'milestones.*.value' => ['required', 'numeric', 'min:0'],
            'milestones.*.due_on' => ['nullable', 'date_format:Y-m-d'],
        ];
    }
}
