<?php

namespace App\Actions\Payroll;

use App\Enums\AdjustmentType;
use App\Enums\EmployeeStatus;
use App\Models\Employee;
use App\Models\EmployeeSalary;
use App\Models\SalaryAdjustment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * The one place a month's salary is worked out, used by the preview, the close
 * and the correction of an existing due alike, so the three can never disagree.
 *
 *   net = salary + bonuses - deductions - advances
 *
 * The salary is the amount in force on the LAST day of the month, so a raise
 * dated mid-month applies to that whole month (open question 3). Nobody is
 * prorated: someone who joined or left mid-month gets a deduction from the owner.
 */
class PayrollCalculator
{
    /**
     * @return array{salary: int, bonuses: int, deductions: int, advances: int, net: int}|null Null when the employee has no salary in force that month.
     */
    public function forEmployee(Employee $employee, CarbonImmutable $month): ?array
    {
        $first = $month->startOfMonth();
        $salary = $employee->salaryOn($first->endOfMonth());

        if ($salary === null) {
            return null;
        }

        $totals = SalaryAdjustment::query()
            ->where('employee_id', $employee->id)
            ->whereDate('month', $first->toDateString())
            ->selectRaw('type, COALESCE(SUM(amount_cents), 0) AS total')
            ->groupBy('type')
            ->pluck('total', 'type');

        $bonuses = (int) ($totals[AdjustmentType::Bonus->value] ?? 0);
        $deductions = (int) ($totals[AdjustmentType::Deduction->value] ?? 0);
        $advances = (int) ($totals[AdjustmentType::Advance->value] ?? 0);

        return [
            'salary' => $salary,
            'bonuses' => $bonuses,
            'deductions' => $deductions,
            'advances' => $advances,
            'net' => $salary + $bonuses - $deductions - $advances,
        ];
    }

    /**
     * The same figures as forEmployee(), for many employees at once: one query for the
     * salaries in force and one for the adjustments, however many people there are.
     *
     * @param  Collection<int, Employee>  $employees
     * @return array<int, array{salary: int, bonuses: int, deductions: int, advances: int, net: int}|null> Keyed by employee id.
     */
    public function forMany(Collection $employees, CarbonImmutable $month): array
    {
        if ($employees->isEmpty()) {
            return [];
        }

        $first = $month->startOfMonth();
        $ids = $employees->pluck('id')->all();

        // Newest raise first, so the first row seen for an employee is the one in force.
        $salaries = [];

        foreach (EmployeeSalary::query()
            ->whereIn('employee_id', $ids)
            ->whereDate('effective_from', '<=', $first->endOfMonth()->toDateString())
            ->orderByDesc('effective_from')
            ->get(['employee_id', 'amount_cents']) as $row) {
            $salaries[$row->employee_id] ??= $row->amount_cents;
        }

        $totals = SalaryAdjustment::query()
            ->whereIn('employee_id', $ids)
            ->whereDate('month', $first->toDateString())
            ->selectRaw('employee_id, type, COALESCE(SUM(amount_cents), 0) AS total')
            ->groupBy('employee_id', 'type')
            // Plain rows: the model would cast `type` into an enum and break the lookup below.
            ->toBase()
            ->get()
            ->groupBy('employee_id');

        $figures = [];

        foreach ($ids as $id) {
            if (! isset($salaries[$id])) {
                $figures[$id] = null;

                continue;
            }

            $byType = ($totals[$id] ?? collect())->pluck('total', 'type');
            $bonuses = (int) ($byType[AdjustmentType::Bonus->value] ?? 0);
            $deductions = (int) ($byType[AdjustmentType::Deduction->value] ?? 0);
            $advances = (int) ($byType[AdjustmentType::Advance->value] ?? 0);

            $figures[$id] = [
                'salary' => $salaries[$id],
                'bonuses' => $bonuses,
                'deductions' => $deductions,
                'advances' => $advances,
                'net' => $salaries[$id] + $bonuses - $deductions - $advances,
            ];
        }

        return $figures;
    }

    /**
     * Who is on the payroll for a month: people who had joined by its last day and were
     * still on the team during it. Someone deactivated mid-month is still paid for that
     * month (the owner adds a deduction if it should be less); someone who left before
     * it began is not.
     *
     * @return Collection<int, Employee>
     */
    public function payableEmployees(CarbonImmutable $month, ?User $actor = null, bool $lock = false): Collection
    {
        $first = $month->startOfMonth()->toDateString();
        $last = $month->startOfMonth()->endOfMonth()->toDateString();

        return Employee::query()
            ->when($actor, fn ($query, User $user) => $query->visibleTo($user))
            ->when($lock, fn ($query) => $query->orderBy('id')->lockForUpdate())
            ->with('office:id,name')
            ->where(fn ($query) => $query
                ->where('status', EmployeeStatus::Active)
                ->orWhereDate('left_on', '>=', $first))
            ->where(fn ($query) => $query->whereNull('hire_date')->orWhereDate('hire_date', '<=', $last))
            ->when(! $lock, fn ($query) => $query->orderBy('name'))
            ->get()
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
    }
}
