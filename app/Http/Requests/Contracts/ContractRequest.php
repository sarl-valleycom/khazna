<?php

namespace App\Http\Requests\Contracts;

use App\Enums\CategoryKind;
use App\Enums\ContractType;
use App\Enums\Cycle;
use App\Enums\MilestoneTrigger;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ContractRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return list<string> */
    public static function creatableTypes(): array
    {
        return [ContractType::SaasRenewal->value, ContractType::CustomProject->value, ContractType::RecurringCost->value];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'supplier_id.required' => __('Choose the supplier this cost is paid to.'),
            'category_id.required' => __('Choose a category for this cost.'),
            'client_id.prohibited' => __('A recurring cost is paid to a supplier, not a client.'),
            'supplier_id.prohibited' => __('Only a recurring cost has a supplier.'),
            'category_id.prohibited' => __('Only a recurring cost has a category.'),
            'milestones.required' => __('Add at least one milestone: a project is paid in stages.'),
            'milestones.prohibited' => __('Milestones are set when a project is created, and rebalanced from its page.'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $isCost = $this->input('type') === ContractType::RecurringCost->value;
        $isProject = $this->input('type') === ContractType::CustomProject->value;
        $creating = $this->route('contract') === null;

        return [
            // Optional so existing callers keep creating SaaS renewals; fixed once a contract exists.
            'type' => ['sometimes', Rule::in(self::creatableTypes())],
            'client_id' => [
                $isCost ? 'prohibited' : 'required',
                'integer',
                Rule::exists('clients', 'id')->whereNull('deleted_at'),
            ],
            'supplier_id' => [
                $isCost ? 'required' : 'prohibited',
                'integer',
                Rule::exists('suppliers', 'id')->whereNull('deleted_at'),
            ],
            'category_id' => [
                $isCost ? 'required' : 'prohibited',
                'integer',
                Rule::exists('categories', 'id')->where('kind', CategoryKind::Cost->value)->where('active', true),
            ],
            'amount_cents' => ['required', 'integer', 'min:1', 'max:99999999999999'],
            // A project is a single agreement paid in milestones, so its cycle is always "once".
            'cycle' => [
                $isProject ? 'sometimes' : 'required',
                Rule::in($isProject ? [Cycle::Once->value] : [Cycle::Monthly->value, Cycle::Quarterly->value, Cycle::Yearly->value]),
            ],
            'starts_on' => ['required', 'date_format:Y-m-d'],
            'ends_on' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:starts_on'],
            'auto_renew' => [$isProject ? 'sometimes' : 'required', 'boolean'],
            'milestones' => [$isProject && $creating ? 'required' : 'prohibited', 'array', 'min:1', 'max:50'],
            'milestones.*.label' => ['required', 'string', 'max:255'],
            'milestones.*.mode' => ['required', Rule::in(['percent', 'amount'])],
            'milestones.*.value' => ['required', 'numeric', 'min:0'],
            'milestones.*.trigger' => ['required', Rule::in(MilestoneTrigger::values())],
            'milestones.*.due_on' => ['nullable', 'date_format:Y-m-d'],
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
