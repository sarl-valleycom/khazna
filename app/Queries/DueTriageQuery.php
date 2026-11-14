<?php

namespace App\Queries;

use App\Actions\Dues\GenerateDues;
use App\Data\TriageFiltersData;
use App\Enums\ContractStatus;
use App\Enums\ContractType;
use App\Enums\DueStatus;
use App\Models\Contract;
use App\Models\Due;
use App\Models\Milestone;
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
    /** How soon before its end date a contract starts asking for a decision. */
    public const ENDING_SOON_DAYS = 30;

    public function __construct(private readonly GenerateDues $generateDues) {}

    /**
     * Count and remaining amounts per tab, from one grouped query. Money to
     * collect and money to pay are kept apart: adding them would be meaningless.
     *
     * @return array<string, array{count: int, collectCents: int, payCents: int}>
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
                 COALESCE(SUM(CASE WHEN direction = 'in' THEN CAST(amount_due_cents AS SIGNED) - CAST(amount_paid_cents AS SIGNED) ELSE 0 END), 0) AS collect_cents,
                 COALESCE(SUM(CASE WHEN direction = 'out' THEN CAST(amount_due_cents AS SIGNED) - CAST(amount_paid_cents AS SIGNED) ELSE 0 END), 0) AS pay_cents",
                [$today->toDateString(), $today->toDateString(), $weekEnd->toDateString(), $monthEnd->toDateString()],
            )
            ->groupBy('bucket')
            ->get();

        $summary = [];

        foreach (['overdue', 'today', 'week', 'month'] as $tab) {
            $row = $rows->firstWhere('bucket', $tab);
            $summary[$tab] = [
                'count' => (int) ($row->due_count ?? 0),
                'collectCents' => (int) ($row->collect_cents ?? 0),
                'payCents' => (int) ($row->pay_cents ?? 0),
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
            ->withParties()
            ->with('postponements.user:id,name')
            ->orderBy('due_on')
            ->orderBy('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Contracts that need someone to decide something:
     *  - active, but no due is left and the cycle has no further date (renew or close it);
     *  - active and ending within the next 30 days, until it is renewed, ended or canceled.
     *
     * A yearly contract whose next due is simply outside the 30-day window is not flagged.
     *
     * @return Collection<int, array{contract: Contract, reason: string}>
     */
    public function attention(User $user, TriageFiltersData $filters, CarbonImmutable $today): Collection
    {
        $unscheduled = $this->attentionBase($user, $filters)
            ->whereDoesntHave('dues', fn (Builder $due): Builder => $due
                ->where('status', '!=', DueStatus::Canceled->value)
                ->where('due_on', '>=', $today->toDateString()))
            // An overdue or part-paid due is already on the Overdue tab; it is not "unscheduled".
            ->whereDoesntHave('dues', fn (Builder $due): Builder => $due
                ->whereIn('status', [DueStatus::Pending->value, DueStatus::Partial->value]))
            ->get()
            ->filter(fn (Contract $contract): bool => $this->generateDues->nextScheduledDate($contract, $today) === null)
            ->map(fn (Contract $contract): array => [
                'contract' => $contract,
                'reason' => (string) __('Active, but no further due is scheduled. Renew it or close it.'),
            ]);

        $seen = $unscheduled->pluck('contract.id')->all();

        $ending = $this->attentionBase($user, $filters)
            ->whereNotNull('ends_on')
            ->whereDate('ends_on', '>=', $today->toDateString())
            ->whereDate('ends_on', '<=', $today->addDays(self::ENDING_SOON_DAYS)->toDateString())
            ->whereNotIn('id', $seen)
            ->orderBy('ends_on')
            ->get()
            ->map(fn (Contract $contract): array => [
                'contract' => $contract,
                'reason' => (string) __('Ends on :date. Renew it, or let it end.', ['date' => $contract->ends_on?->format('d M Y')]),
            ]);

        // FR-TRI-03: a milestone was delivered, but nobody has asked the client to pay yet.
        $unrequested = Milestone::query()
            ->whereNotNull('delivered_at')
            ->whereNull('requested_at')
            ->whereHas('due', fn (Builder $due): Builder => $due->whereIn('status', [DueStatus::Pending->value, DueStatus::Partial->value]))
            ->whereIn('contract_id', $this->attentionBase($user, $filters)->reorder()->select('contracts.id'))
            ->with(['contract' => fn ($contract) => $contract->withParties()])
            ->orderBy('delivered_at')
            ->get()
            ->flatMap(fn (Milestone $milestone): array => $milestone->contract === null ? [] : [[
                'contract' => $milestone->contract,
                'reason' => (string) __('Milestone “:label” was delivered on :date, but the client has not been asked to pay.', [
                    'label' => $milestone->label,
                    'date' => $milestone->delivered_at?->format('d M Y'),
                ]),
            ]]);

        return $unrequested->concat($unscheduled)->concat($ending)->values();
    }

    /**
     * Active contracts the user may see, narrowed by the triage filters.
     *
     * @return Builder<Contract>
     */
    private function attentionBase(User $user, TriageFiltersData $filters): Builder
    {
        $types = match ($filters->kind) {
            'saas' => [ContractType::SaasRenewal->value],
            'project' => [ContractType::CustomProject->value],
            'expense' => [ContractType::RecurringCost->value],
            '' => null,
            default => [],
        };

        return Contract::query()
            ->visibleTo($user)
            ->where('status', ContractStatus::Active)
            ->when($filters->direction !== '', fn (Builder $query): Builder => $query->where('direction', $filters->direction))
            ->when($types !== null, fn (Builder $query): Builder => $query->whereIn('type', $types ?? []))
            ->when($filters->client !== null, fn (Builder $query): Builder => $query->where('client_id', $filters->client))
            ->when($filters->office !== null, fn (Builder $query): Builder => $query->where('office_id', $filters->office))
            ->when($filters->assignee !== null, fn (Builder $query): Builder => $query->whereHas(
                'client',
                fn (Builder $client): Builder => $client->where('account_manager_id', $filters->assignee),
            ))
            ->withParties()
            ->orderBy('id');
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
