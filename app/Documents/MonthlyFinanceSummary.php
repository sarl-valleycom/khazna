<?php

namespace App\Documents;

use App\Actions\Payroll\PayrollCalculator;
use App\Enums\EmployeeStatus;
use App\Models\Employee;
use App\Models\Office;
use App\Models\User;
use App\Queries\CashReportQuery;
use App\Support\Metrics\DashboardMetrics;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * The month on one page: money in, money out, what clients still owe, and, for
 * someone who may see salaries, the salary total. It reuses the cash report and
 * the dashboard numbers, so the PDF can never disagree with the screens.
 *
 * Amounts stay in integer centimes until the page prints them.
 */
class MonthlyFinanceSummary
{
    public function __construct(
        private readonly CashReportQuery $cash,
        private readonly PayrollCalculator $payroll,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(User $viewer, CarbonImmutable $month, ?int $officeId): array
    {
        $from = $month->startOfMonth();
        $to = $month->endOfMonth()->startOfDay();

        $report = $this->cash->handle($viewer, $from, $to, $officeId, null);
        $metrics = new DashboardMetrics($viewer, $officeId);
        $canSeeSalaries = $viewer->can('view-salaries');

        return [
            'month' => $from,
            'office' => $officeId === null ? null : Office::query()->find($officeId)?->name,
            'inCents' => $report['totals']['inCents'],
            'outCents' => $report['totals']['outCents'],
            'resultCents' => $report['totals']['resultCents'],
            'rows' => $report['rows'],
            'offices' => $officeId === null ? $report['offices'] : [],
            'includesPayroll' => $report['includesPayroll'],
            'outstandingCents' => $metrics->outstanding(),
            'overdueCents' => $metrics->overdue(),
            // The salary block exists only for someone with the salary permission. For anyone
            // else it is not built, so no total can leak through a rounded-up difference.
            'salary' => $canSeeSalaries ? $this->salary($viewer, $from, $officeId) : null,
        ];
    }

    /**
     * @return array{people: int, baseCents: int, bonusCents: int, deductionCents: int, advanceCents: int, netCents: int}
     */
    private function salary(User $viewer, CarbonImmutable $month, ?int $officeId): array
    {
        $employees = Employee::query()
            ->visibleTo($viewer)
            ->when($officeId !== null, fn (Builder $query) => $query->where('office_id', $officeId))
            ->where(fn (Builder $query) => $query
                ->where('status', EmployeeStatus::Active)
                ->orWhereDate('left_on', '>=', $month->toDateString()))
            ->where(fn (Builder $query) => $query
                ->whereNull('hire_date')
                ->orWhereDate('hire_date', '<=', $month->endOfMonth()->toDateString()))
            ->get();

        $figures = collect($this->payroll->forMany($employees, $month))->filter();

        return [
            'people' => $figures->count(),
            'baseCents' => (int) $figures->sum('salary'),
            'bonusCents' => (int) $figures->sum('bonuses'),
            'deductionCents' => (int) $figures->sum('deductions'),
            'advanceCents' => (int) $figures->sum('advances'),
            'netCents' => (int) $figures->sum('net'),
        ];
    }
}
