<?php

namespace App\Actions\Dues;

use App\Data\DueGenerationReport;
use App\Enums\ContractStatus;
use App\Enums\ContractType;
use App\Enums\Cycle;
use App\Enums\DueKind;
use App\Enums\DueStatus;
use App\Models\Client;
use App\Models\Contract;
use App\Models\Due;
use App\Support\AlgerianDate;
use App\Support\AuditContext;
use App\Support\CycleArithmetic;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class GenerateDues
{
    public function handle(?CarbonImmutable $today = null): DueGenerationReport
    {
        $today = $today?->startOfDay() ?? AlgerianDate::today();
        $report = DueGenerationReport::empty();

        Contract::query()
            ->where('status', ContractStatus::Active)
            ->whereHas('client')
            ->lazyById(200)
            ->each(function (Contract $contract) use ($today, &$report): void {
                $report = $report->merge($this->generate($contract, $today, false));
            });

        return $report;
    }

    /**
     * @param  bool  $revive  Bring back dues that a pause or reschedule canceled, for
     *                        periods this contract bills again. Only the explicit
     *                        resume and edit paths pass this, never the nightly run.
     */
    public function forContract(Contract $contract, ?CarbonImmutable $today = null, bool $revive = false): DueGenerationReport
    {
        // The nightly run already filters out trashed clients in its query; a single
        // contract is checked here with one cheap query instead of lazy-loading.
        if ($contract->status !== ContractStatus::Active
            || ! Client::query()->whereKey($contract->client_id)->exists()) {
            return DueGenerationReport::empty();
        }

        return $this->generate($contract, $today, $revive);
    }

    private function generate(Contract $contract, ?CarbonImmutable $today, bool $revive): DueGenerationReport
    {
        $today = $today?->startOfDay() ?? AlgerianDate::today();
        $from = $contract->starts_on->gt($today) ? $contract->starts_on : $today;
        $to = $today->addDays(30);

        if ($contract->ends_on !== null) {
            if ($contract->ends_on->lt($from)) {
                return DueGenerationReport::empty();
            }

            if ($contract->ends_on->lt($to)) {
                $to = $contract->ends_on;
            }
        }

        $created = 0;
        $skipped = 0;

        foreach ($this->dates($contract, $from, $to) as $date) {
            $inserted = DB::table('dues')->insertOrIgnore([
                'kind' => $this->kind($contract)->value,
                'direction' => $contract->direction->value,
                'contract_id' => $contract->id,
                'client_id' => $contract->client_id,
                'office_id' => $contract->office_id,
                'period' => $date->toDateString(),
                'due_on' => $date->toDateString(),
                'amount_due_cents' => $contract->amount_cents,
                'amount_paid_cents' => 0,
                'status' => DueStatus::Pending->value,
                'description' => null,
                'canceled_reason' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if ($inserted === 1) {
                $created++;
            } elseif ($revive && $this->revive($contract, $date)) {
                $created++;
            } else {
                $skipped++;
            }
        }

        return new DueGenerationReport(
            created: $created,
            skipped: $skipped,
            contractIds: $created > 0 ? [$contract->id] : [],
        );
    }

    private function revive(Contract $contract, CarbonImmutable $date): bool
    {
        $due = Due::query()
            ->where('contract_id', $contract->id)
            ->whereDate('period', $date->toDateString())
            ->where('status', DueStatus::Canceled)
            ->where('canceled_by_contract', true)
            ->where('amount_paid_cents', 0)
            ->lockForUpdate()
            ->first();

        if ($due === null) {
            return false;
        }

        app(AuditContext::class)->withReason(__('Contract reactivated or rescheduled.'), fn () => $due->update([
            'status' => DueStatus::Pending,
            'amount_due_cents' => $contract->amount_cents,
            'canceled_reason' => null,
            'canceled_by_contract' => false,
        ]));

        return true;
    }

    /**
     * The next date, today or later, on which this contract would bill, or null
     * when it never will again (ended, or a finished one-time contract).
     */
    public function nextScheduledDate(Contract $contract, ?CarbonImmutable $today = null): ?CarbonImmutable
    {
        $today = $today?->startOfDay() ?? AlgerianDate::today();
        $from = $contract->starts_on->gt($today) ? $contract->starts_on : $today;

        if ($contract->ends_on !== null && $contract->ends_on->lt($from)) {
            return null;
        }

        if ($contract->cycle === Cycle::Once) {
            return $contract->starts_on->toDateString() >= $today->toDateString() ? $contract->starts_on : null;
        }

        $to = $contract->ends_on ?? $from->addYears(2);

        return $this->dates($contract, $from, $to)[0] ?? null;
    }

    /**
     * @return list<CarbonImmutable>
     */
    private function dates(Contract $contract, CarbonImmutable $from, CarbonImmutable $to): array
    {
        if ($contract->cycle === Cycle::Once) {
            // A one-time amount is owed once, on its date. If that date has already
            // passed and it was never billed, it is simply overdue: create it. The
            // unique (contract, period) key keeps every later run from adding another.
            return $contract->starts_on->toDateString() <= $to->toDateString()
                ? [$contract->starts_on]
                : [];
        }

        $months = $contract->cycle->months();

        if ($months <= 0) {
            return [];
        }

        $anchor = $contract->starts_on->startOfDay();
        $monthOffset = ($from->year - $anchor->year) * 12 + ($from->month - $anchor->month);
        $step = intdiv(max(0, $monthOffset), $months);
        $date = CycleArithmetic::addMonths($anchor, $step * $months);

        while ($date->toDateString() < $from->toDateString()) {
            $step++;
            $date = CycleArithmetic::addMonths($anchor, $step * $months);
        }

        $dates = [];

        // Calendar dates, not instants: the anchor carries a time of day and `to` does not.
        while ($date->toDateString() <= $to->toDateString()) {
            $dates[] = $date;
            $step++;
            $date = CycleArithmetic::addMonths($anchor, $step * $months);
        }

        return $dates;
    }

    private function kind(Contract $contract): DueKind
    {
        return match ($contract->type) {
            ContractType::SaasRenewal => DueKind::Saas,
            ContractType::CustomProject => DueKind::Project,
            ContractType::RecurringCost => DueKind::Expense,
        };
    }
}
