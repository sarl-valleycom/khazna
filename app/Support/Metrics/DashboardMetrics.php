<?php

namespace App\Support\Metrics;

use App\Enums\ContractStatus;
use App\Enums\Cycle;
use App\Enums\Direction;
use App\Enums\DueStatus;
use App\Enums\EmployeeStatus;
use App\Models\Account;
use App\Models\Contract;
use App\Models\Due;
use App\Models\Employee;
use App\Models\EmployeeSalary;
use App\Models\Payment;
use App\Models\User;
use App\Support\AlgerianDate;
use App\Support\Money;
use App\Support\Period;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * The dashboard numbers, one method each, so each can be tested against a
 * dataset worked out by hand (FR-DSH-02). Every method respects what the user may
 * see and, when given, narrows to one office. Nothing here is cached: a card only
 * gets caching if it ever takes more than 300 ms.
 */
class DashboardMetrics extends Metrics
{
    public function __construct(
        private readonly User $user,
        private readonly ?int $officeId = null,
        ?CarbonImmutable $today = null,
    ) {
        $this->today = ($today ?? AlgerianDate::today())->startOfDay();

        parent::__construct(Period::monthly($this->today));
    }

    private readonly CarbonImmutable $today;

    /** Money received this month on money-in dues, not counting reversed payments. Attributed to the office of the account it went into. */
    public function collectedThisMonth(): int
    {
        $start = $this->period->anchor;

        return (int) Payment::query()
            ->visibleTo($this->user)
            ->notReversed()
            ->whereDate('paid_on', '>=', $start->toDateString())
            ->whereDate('paid_on', '<=', $start->endOfMonth()->toDateString())
            ->whereHas('due', fn (Builder $due): Builder => $due->where('direction', Direction::In->value))
            ->when($this->officeId !== null, fn (Builder $query): Builder => $query->whereHas(
                'account',
                fn (Builder $account): Builder => $account->where('office_id', $this->officeId),
            ))
            ->sum('amount_cents');
    }

    /** What clients still owe: the remaining amount on every open money-in due. */
    public function outstanding(): int
    {
        return $this->remaining($this->openReceivables());
    }

    /** The part of outstanding that is already late. A milestone waiting for delivery has no date, so it is never late. */
    public function overdue(): int
    {
        return $this->remaining(
            $this->openReceivables()
                ->whereNotNull('due_on')
                ->whereDate('due_on', '<', $this->today->toDateString()),
        );
    }

    /** Monthly recurring revenue: live money-in contracts normalised to a month (monthly x1, quarterly /3, yearly /12). */
    public function mrr(): int
    {
        $total = 0;

        Contract::query()
            ->visibleTo($this->user)
            ->where('status', ContractStatus::Active)
            ->where('direction', Direction::In->value)
            ->whereIn('cycle', [Cycle::Monthly->value, Cycle::Quarterly->value, Cycle::Yearly->value])
            ->whereDate('starts_on', '<=', $this->today->toDateString())
            ->where(fn (Builder $query): Builder => $query->whereNull('ends_on')->orWhereDate('ends_on', '>=', $this->today->toDateString()))
            ->when($this->officeId !== null, fn (Builder $query): Builder => $query->where('office_id', $this->officeId))
            ->get(['id', 'amount_cents', 'cycle'])
            ->each(function (Contract $contract) use (&$total): void {
                $total += Money::fromCents($contract->amount_cents)->multiplyByRatio(1, $contract->cycle->months())->cents;
            });

        return $total;
    }

    /**
     * Contracts that have money coming due in the next 30 days.
     *
     * @return array{count: int, cents: int}
     */
    public function upcomingRenewals(): array
    {
        $query = $this->openReceivables()
            ->whereNotNull('contract_id')
            // A project's milestones fall due but nothing is renewing: only cycle contracts renew.
            ->whereNull('milestone_id')
            ->whereDate('due_on', '>=', $this->today->toDateString())
            ->whereDate('due_on', '<=', $this->today->addDays(30)->toDateString());

        return [
            'count' => (int) (clone $query)->distinct()->count('contract_id'),
            'cents' => $this->remaining($query),
        ];
    }

    /** What the active team earns in a month. Only for someone with the salary permission; anyone else gets null and the query never runs. */
    public function salaryBill(): ?int
    {
        if (! $this->user->can('view-salaries')) {
            return null;
        }

        $current = EmployeeSalary::query()
            ->select('amount_cents')
            ->whereColumn('employee_id', 'employees.id')
            ->whereDate('effective_from', '<=', $this->today->toDateString())
            ->orderByDesc('effective_from')
            ->limit(1);

        return (int) Employee::query()
            ->visibleTo($this->user)
            ->where('status', EmployeeStatus::Active)
            ->when($this->officeId !== null, fn (Builder $query): Builder => $query->where('office_id', $this->officeId))
            ->addSelect(['employees.id', 'current_salary' => $current])
            ->get()
            ->sum('current_salary');
    }

    /**
     * Each account's balance, grouped by office (FR-DSH-08). Empty for anyone who may not see accounts.
     *
     * @return array<int, array{officeId: int, officeName: string, totalCents: int, accounts: array<int, array{id: int, name: string, typeLabel: string, balanceCents: int, active: bool}>}>
     */
    public function accountBalances(): array
    {
        if (! $this->user->can('viewAny', Account::class)) {
            return [];
        }

        return Account::query()
            ->visibleTo($this->user)
            ->withBalance()
            ->with('office:id,name')
            ->when($this->officeId !== null, fn (Builder $query): Builder => $query->where('office_id', $this->officeId))
            ->orderBy('office_id')
            ->orderBy('name')
            ->get()
            ->groupBy('office_id')
            ->map(fn ($accounts, $officeId): array => [
                'officeId' => (int) $officeId,
                'officeName' => $accounts->first()?->office->name ?? '',
                'totalCents' => (int) $accounts->where('active', true)->sum('balance_cents'),
                'accounts' => $accounts->map(fn (Account $account): array => [
                    'id' => $account->id,
                    'name' => $account->name,
                    'typeLabel' => $account->type->label(),
                    'balanceCents' => (int) $account->balance_cents,
                    'active' => $account->active,
                ])->values()->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * @return Builder<Due>
     */
    private function openReceivables(): Builder
    {
        return Due::query()
            ->visibleTo($this->user)
            ->where('direction', Direction::In->value)
            ->whereIn('status', [DueStatus::Pending->value, DueStatus::Partial->value])
            ->when($this->officeId !== null, fn (Builder $query): Builder => $query->where('office_id', $this->officeId));
    }

    /**
     * @param  Builder<Due>  $query
     */
    private function remaining(Builder $query): int
    {
        return (int) $query->sum(DB::raw('CAST(amount_due_cents AS SIGNED) - CAST(amount_paid_cents AS SIGNED)'));
    }
}
