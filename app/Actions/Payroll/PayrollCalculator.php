<?php

namespace App\Actions\Payroll;

use App\Enums\AdjustmentType;
use App\Enums\EmployeeStatus;
use App\Models\Employee;
use App\Models\SalaryAdjustment;
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
     * Who is on the payroll for a month: people who had joined by its last day and were
     * still on the team during it. Someone deactivated mid-month is still paid for that
     * month (the owner adds a deduction if it should be less); someone who left before
     * it began is not.
     *
     * @return Collection<int, Employee>
     */
    public function payableEmployees(CarbonImmutable $month): Collection
    {
        $first = $month->startOfMonth()->toDateString();
        $last = $month->startOfMonth()->endOfMonth()->toDateString();

        return Employee::query()
            ->with('office:id,name')
            ->where(fn ($query) => $query
                ->where('status', EmployeeStatus::Active)
                ->orWhereDate('left_on', '>=', $first))
            ->where(fn ($query) => $query->whereNull('hire_date')->orWhereDate('hire_date', '<=', $last))
            ->orderBy('name')
            ->get();
    }
}
