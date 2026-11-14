<?php

namespace App\Queries;

use App\Actions\Dues\GenerateDues;
use App\Data\TriageFiltersData;
use App\Enums\ContractStatus;
use App\Enums\DueStatus;
use App\Models\Contract;
use App\Models\Due;
use App\Models\Setting;
use App\Models\User;
use App\Support\AlgerianDate;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * The home screen's open dues. The tabs are mutually exclusive, so their counts
 * add up, and every boundary is an Algiers calendar date:
 *
 *   overdue   due_on < today
 *   today     due_on = today
 *   week      today < due_on <= end of week
 *   month     end of week < due_on <= end of month
 *   attention an active contract with no further due scheduled
 */
class DueTriageQuery
{
    public function __construct(private readonly GenerateDues $generateDues) {}

    /**
     * Count and remaining amount per tab, from one grouped query.
     *
     * @return array<string, array{count: int, totalCents: int}>
     */
    public function summary(User $user, TriageFiltersData $filters, CarbonImmutable $today): array
    {
        [$weekEnd, $monthEnd] = $this->bounds($today);

        $rows = $this->base($user, $filters)
            ->selectRaw(
                "CASE
                    WHEN due_on < ? THEN 'overdue'
                    WHEN due_on = ? THEN 'today'
                    WHEN due_on <= ? THEN 'week'
                    WHEN due_on <= ? THEN 'month'
                 END AS bucket,
                 COUNT(*) AS due_count,
                 COALESCE(SUM(CAST(amount_due_cents AS SIGNED) - CAST(amount_paid_cents AS SIGNED)), 0) AS remaining_cents",
                [$today->toDateString(), $today->toDateString(), $weekEnd->toDateString(), $monthEnd->toDateString()],
            )
            ->groupBy('bucket')
            ->get();

        $summary = [];

        foreach (['overdue', 'today', 'week', 'month'] as $tab) {
            $row = $rows->firstWhere('bucket', $tab);
            $summary[$tab] = [
                'count' => (int) ($row->due_count ?? 0),
                'totalCents' => (int) ($row->remaining_cents ?? 0),
            ];
        }

        return $summary;
    }

    /**
     * @return LengthAwarePaginator<int, Due>
     */
    public function rows(User $user, TriageFiltersData $filters, CarbonImmutable $today, int $perPage = 25): LengthAwarePaginator
    {
        [$weekEnd, $monthEnd] = $this->bounds($today);
        $query = $this->base($user, $filters);

        match ($filters->tab) {
            'overdue' => $query->where('due_on', '<', $today->toDateString()),
            'today' => $query->where('due_on', $today->toDateString()),
            'week' => $query->where('due_on', '>', $today->toDateString())->where('due_on', '<=', $weekEnd->toDateString()),
            'month' => $query->where('due_on', '>', $weekEnd->toDateString())->where('due_on', '<=', $monthEnd->toDateString()),
            default => $query->whereRaw('1 = 0'),
        };

        return $query
            ->with(['client:id,name,account_manager_id', 'office:id,name'])
            ->orderBy('due_on')
            ->orderBy('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Active contracts that will never bill again: no due is left on or after
     * today and the cycle has no further date. A yearly contract whose next due
     * is simply outside the 30-day window is not flagged.
     *
     * @return Collection<int, Contract>
     */
    public function attention(User $user, TriageFiltersData $filters, CarbonImmutable $today): Collection
    {
        if ($filters->direction === 'out' || ($filters->kind !== '' && $filters->kind !== 'saas')) {
            return new Collection;
        }

        $contracts = Contract::query()
            ->visibleTo($user)
            ->where('status', ContractStatus::Active)
            ->whereDoesntHave('dues', fn (Builder $due): Builder => $due
                ->where('status', '!=', DueStatus::Canceled->value)
                ->where('due_on', '>=', $today->toDateString()))
            ->when($filters->client !== null, fn (Builder $query): Builder => $query->where('client_id', $filters->client))
            ->when($filters->office !== null, fn (Builder $query): Builder => $query->where('office_id', $filters->office))
            ->when($filters->assignee !== null, fn (Builder $query): Builder => $query->whereHas(
                'client',
                fn (Builder $client): Builder => $client->where('account_manager_id', $filters->assignee),
            ))
            ->with(['client:id,name,account_manager_id', 'office:id,name'])
            ->orderBy('id')
            ->get();

        return $contracts
            ->filter(fn (Contract $contract): bool => $this->generateDues->nextScheduledDate($contract, $today) === null)
            ->values();
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function bounds(CarbonImmutable $today): array
    {
        $weekStart = Setting::current(Setting::WEEK_START, (string) config('khazna.week_starts_on'));

        return [
            AlgerianDate::endOfWeek($today, $weekStart)->startOfDay(),
            AlgerianDate::endOfMonth($today)->startOfDay(),
        ];
    }

    /**
     * @return Builder<Due>
     */
    private function base(User $user, TriageFiltersData $filters): Builder
    {
        return Due::query()
            ->visibleTo($user)
            ->whereIn('status', [DueStatus::Pending->value, DueStatus::Partial->value])
            ->when($filters->direction !== '', fn (Builder $query): Builder => $query->where('direction', $filters->direction))
            ->when($filters->kind !== '', fn (Builder $query): Builder => $query->where('kind', $filters->kind))
            ->when($filters->client !== null, fn (Builder $query): Builder => $query->where('client_id', $filters->client))
            ->when($filters->office !== null, fn (Builder $query): Builder => $query->where('office_id', $filters->office))
            ->when($filters->assignee !== null, fn (Builder $query): Builder => $query->whereHas(
                'client',
                fn (Builder $client): Builder => $client->where('account_manager_id', $filters->assignee),
            ));
    }
}
