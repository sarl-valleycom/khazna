<?php

namespace App\Actions\Payroll;

use App\Actions\Ledger\LedgerWriter;
use App\Enums\AdjustmentType;
use App\Enums\Direction;
use App\Enums\PaymentMethod;
use App\Models\Account;
use App\Models\Category;
use App\Models\Employee;
use App\Models\SalaryAdjustment;
use App\Models\User;
use App\Support\AlgerianDate;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AddAdjustment
{
    public function __construct(
        private readonly LedgerWriter $ledger,
        private readonly SyncSalaryDue $sync,
    ) {}

    /**
     * Add an advance, bonus or deduction to a month (FR-SAL-03). An advance is money
     * leaving today, so it also writes its ledger entry on the chosen account in the
     * same transaction. Adjustments are only ever added, never edited or deleted:
     * a mistake is corrected with an opposite adjustment.
     *
     * @param  array<string, mixed>  $data  Validated AdjustmentRequest data with `month` as a date (YYYY-MM-01).
     */
    public function handle(User $actor, Employee $employee, array $data): SalaryAdjustment
    {
        $type = AdjustmentType::from((string) $data['type']);
        $month = CarbonImmutable::parse((string) $data['month'])->startOfMonth();
        $reason = trim((string) $data['reason']);

        if ($reason === '') {
            throw ValidationException::withMessages(['reason' => __('Enter a reason.')]);
        }

        return DB::transaction(function () use ($actor, $employee, $data, $type, $month, $reason): SalaryAdjustment {
            $adjustment = SalaryAdjustment::query()->create([
                'employee_id' => $employee->id,
                'month' => $month->toDateString(),
                'type' => $type,
                'amount_cents' => (int) $data['amount_cents'],
                'reason' => $reason,
                'created_by' => $actor->id,
            ]);

            if ($type === AdjustmentType::Advance) {
                $this->payAdvance($actor, $employee, $adjustment, $data);
            }

            // If the month is already closed, its salary due follows the adjustment.
            $this->sync->handle($employee, $month, isset($data['correction_reason']) ? (string) $data['correction_reason'] : null);

            return $adjustment->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function payAdvance(User $actor, Employee $employee, SalaryAdjustment $adjustment, array $data): void
    {
        foreach (['paid_on' => __('Choose the day the advance was paid.'), 'method' => __('Choose how it was paid.'), 'account_id' => __('Choose the account it was paid from.')] as $field => $message) {
            if (empty($data[$field])) {
                throw ValidationException::withMessages([$field => $message]);
            }
        }

        $paidOn = AlgerianDate::toAlgiers((string) $data['paid_on'])->startOfDay();

        if ($paidOn->gt(AlgerianDate::today())) {
            throw ValidationException::withMessages(['paid_on' => __('The payment date cannot be in the future.')]);
        }

        $account = Account::query()
            ->visibleTo($actor)
            ->where('active', true)
            ->lockForUpdate()
            ->find((int) $data['account_id']);

        if ($account === null) {
            throw ValidationException::withMessages(['account_id' => __('Choose one of the accounts available to you.')]);
        }

        $adjustment->update([
            'paid_on' => $paidOn->toDateString(),
            'method' => PaymentMethod::from((string) $data['method']),
            'account_id' => $account->id,
        ]);

        $this->ledger->record(
            account: $account,
            direction: Direction::Out,
            amountCents: $adjustment->amount_cents,
            entryDate: $paidOn,
            source: $adjustment,
            by: $actor,
            description: __('Advance: :name', ['name' => $employee->name]),
            dateField: 'paid_on',
            categoryId: Category::named(Category::SALARIES)->id,
            isPayroll: true,
        );
    }
}
