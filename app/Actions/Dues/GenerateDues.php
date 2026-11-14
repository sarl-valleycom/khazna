<?php

namespace App\Actions\Dues;

use App\Data\DueGenerationReport;
use App\Enums\ContractStatus;
use App\Enums\ContractType;
use App\Enums\Cycle;
use App\Enums\DueKind;
use App\Enums\DueStatus;
use App\Models\Contract;
use App\Support\AlgerianDate;
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
            ->lazyById(200)
            ->each(function (Contract $contract) use ($today, &$report): void {
                $report = $report->merge($this->forContract($contract, $today));
            });

        return $report;
    }

    public function forContract(Contract $contract, ?CarbonImmutable $today = null): DueGenerationReport
    {
        if ($contract->status !== ContractStatus::Active) {
            return DueGenerationReport::empty();
        }

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

            $inserted === 1 ? $created++ : $skipped++;
        }

        return new DueGenerationReport(
            created: $created,
            skipped: $skipped,
            contractIds: $created > 0 ? [$contract->id] : [],
        );
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

        $to = $contract->ends_on ?? $from->addYears(2);

        return $this->dates($contract, $from, $to)[0] ?? null;
    }

    /**
     * @return list<CarbonImmutable>
     */
    private function dates(Contract $contract, CarbonImmutable $from, CarbonImmutable $to): array
    {
        if ($contract->cycle === Cycle::Once) {
            return $contract->starts_on->betweenIncluded($from, $to)
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

        while ($date->lt($from)) {
            $step++;
            $date = CycleArithmetic::addMonths($anchor, $step * $months);
        }

        $dates = [];

        while ($date->lte($to)) {
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
