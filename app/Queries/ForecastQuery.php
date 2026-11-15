<?php

namespace App\Queries;

use App\Enums\Direction;
use App\Enums\DueKind;
use App\Enums\DueStatus;
use App\Models\Account;
use App\Models\Due;
use App\Models\Office;
use App\Models\Reconciliation;
use App\Models\User;
use App\Support\AlgerianDate;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Cash forecast, bucketed by week (FR-DSH-03..05, 2.7).
 *
 * It starts from the real balances of the accounts in scope, never a typed figure.
 * An office is forecast with its own accounts against its own dues, because cash in
 * one office cannot pay the other office's salaries until it is transferred: the
 * company total can look healthy while one office is short (SRS scenario 24).
 *
 * Rules:
 *  - an open money-in due counts at its remaining amount on its due date;
 *  - an overdue money-out due (an unpaid salary, say) counts on day 0;
 *  - an overdue money-in due is listed apart and counted only when asked, because
 *    counting money that is already late is optimistic;
 *  - a due with no date (a milestone waiting for delivery) is never counted.
 *
 * Salary dues exist only for people with the salary permission (Due::visibleTo),
 * so a user without it gets a forecast without payroll rather than a hidden figure.
 */
class ForecastQuery
{
    public const STALE_RECONCILIATION_DAYS = 30;

    /**
     * @return array{
     *     days: int,
     *     includesPayroll: bool,
     *     company: array<string, mixed>,
     *     offices: array<int, array<string, mixed>>
     * }
     */
    public function handle(User $user, int $days, bool $includeOverdueIn, ?CarbonImmutable $today = null): array
    {
        $today = ($today ?? AlgerianDate::today())->startOfDay();
        $days = in_array($days, [30, 60, 90], true) ? $days : 30;
        $end = $today->addDays($days - 1);

        $offices = Office::query()->visibleTo($user)->where('active', true)->orderBy('name')->get(['id', 'name']);

        $accounts = Account::query()
            ->visibleTo($user)
            ->where('active', true)
            ->with('office:id,name')
            ->withBalance()
            ->get();

        $reconciled = Reconciliation::query()
            ->selectRaw('account_id, MAX(as_of) AS last_as_of')
            ->groupBy('account_id')
            ->pluck('last_as_of', 'account_id');

        $dues = Due::query()
            ->visibleTo($user)
            ->whereIn('status', [DueStatus::Pending->value, DueStatus::Partial->value])
            ->whereNotNull('due_on')
            ->whereDate('due_on', '<=', $end->toDateString())
            ->withParties()
            ->orderBy('due_on')
            ->orderBy('id')
            ->get();

        $onlyOffice = $offices->count() === 1 ? $offices->first() : null;

        $scopes = $offices->map(fn (Office $office): array => $this->scope(
            $office->name,
            $office->id,
            $accounts->where('office_id', $office->id),
            $dues->where('office_id', $office->id),
            $reconciled,
            $today,
            $days,
            $includeOverdueIn,
        ))->values()->all();

        return [
            'days' => $days,
            'includesPayroll' => $user->can('view-salaries'),
            'company' => $this->scope(
                $onlyOffice !== null ? $onlyOffice->name : __('Company'),
                null,
                $accounts,
                $dues,
                $reconciled,
                $today,
                $days,
                $includeOverdueIn,
            ),
            'offices' => $scopes,
        ];
    }

    /**
     * @param  Collection<int, Account>  $accounts
     * @param  Collection<int, Due>  $dues
     * @param  Collection<int, mixed>  $reconciled
     * @return array<string, mixed>
     */
    private function scope(string $label, ?int $officeId, Collection $accounts, Collection $dues, Collection $reconciled, CarbonImmutable $today, int $days, bool $includeOverdueIn): array
    {
        $bucketCount = (int) ceil($days / 7);
        $buckets = [];

        for ($index = 0; $index < $bucketCount; $index++) {
            $from = $today->addDays($index * 7);
            $to = $today->addDays(min($days, ($index + 1) * 7) - 1);
            $buckets[$index] = [
                'index' => $index,
                'label' => 'W'.($index + 1),
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'inCents' => 0,
                'outCents' => 0,
                'balanceCents' => 0,
                'belowPayroll' => false,
                'negative' => false,
                'dues' => [],
            ];
        }

        $overdueIn = ['count' => 0, 'cents' => 0, 'dues' => []];

        foreach ($dues as $due) {
            $remaining = $due->remainingCents();

            if ($remaining <= 0 || $due->due_on === null) {
                continue;
            }

            $isOverdue = $due->due_on->lt($today);
            $isIn = $due->direction === Direction::In;

            if ($isOverdue && $isIn) {
                $overdueIn['count']++;
                $overdueIn['cents'] += $remaining;
                $overdueIn['dues'][] = $this->line($due, $remaining, true);

                // Optimistic view: count it on day 0 as if it were collected immediately.
                if (! $includeOverdueIn) {
                    continue;
                }
            }

            $index = $isOverdue ? 0 : (int) floor($today->diffInDays($due->due_on) / 7);

            if (! isset($buckets[$index])) {
                continue;
            }

            $buckets[$index][$isIn ? 'inCents' : 'outCents'] += $remaining;
            $buckets[$index]['dues'][] = $this->line($due, $remaining, $isOverdue);
        }

        $start = (int) $accounts->sum('balance_cents');
        $nextPayroll = $this->nextPayrollCents($dues);
        $running = $start;
        $lowest = $start;
        $firstNegative = null;

        foreach ($buckets as $index => $bucket) {
            $running += $bucket['inCents'] - $bucket['outCents'];
            $buckets[$index]['balanceCents'] = $running;
            $buckets[$index]['negative'] = $running < 0;
            $buckets[$index]['belowPayroll'] = $nextPayroll !== null && $running < $nextPayroll;
            $lowest = min($lowest, $running);
            $firstNegative ??= $running < 0 ? $bucket['label'] : null;
        }

        $stale = $accounts
            ->filter(function (Account $account) use ($reconciled, $today): bool {
                $last = $reconciled->get($account->id);

                return $last === null
                    || CarbonImmutable::parse((string) $last)->lt($today->subDays(self::STALE_RECONCILIATION_DAYS));
            })
            ->map(fn (Account $account): array => [
                'id' => $account->id,
                'name' => $account->name,
                'officeName' => $account->office->name ?? null,
                'lastReconciled' => $reconciled->get($account->id) === null ? null : CarbonImmutable::parse((string) $reconciled->get($account->id))->toDateString(),
            ])
            ->values()
            ->all();

        return [
            'label' => $label,
            'officeId' => $officeId,
            'startBalanceCents' => $start,
            'endBalanceCents' => $running,
            'lowestBalanceCents' => $lowest,
            'firstNegativeBucket' => $firstNegative,
            'nextPayrollCents' => $nextPayroll,
            'buckets' => array_values($buckets),
            'overdueIn' => $overdueIn,
            'staleAccounts' => $stale,
        ];
    }

    /**
     * The next salary run: everything open on the earliest salary date (overdue ones
     * count as the earliest). Null when there are no salary dues to see.
     *
     * @param  Collection<int, Due>  $dues
     */
    private function nextPayrollCents(Collection $dues): ?int
    {
        $salary = $dues->filter(fn (Due $due): bool => $due->kind === DueKind::Salary && $due->remainingCents() > 0);

        if ($salary->isEmpty()) {
            return null;
        }

        $earliest = $salary->min(fn (Due $due): string => (string) $due->due_on?->toDateString());

        return (int) $salary
            ->filter(fn (Due $due): bool => (string) $due->due_on?->toDateString() === $earliest)
            ->sum(fn (Due $due): int => $due->remainingCents());
    }

    /**
     * @return array<string, mixed>
     */
    private function line(Due $due, int $remaining, bool $overdue): array
    {
        return [
            'id' => $due->id,
            'partyName' => $due->partyName(),
            'kindLabel' => $due->kind->label(),
            'direction' => $due->direction->value,
            'dueOn' => $due->due_on?->toDateString(),
            'remainingCents' => $remaining,
            'overdue' => $overdue,
            'description' => $due->description,
        ];
    }
}
