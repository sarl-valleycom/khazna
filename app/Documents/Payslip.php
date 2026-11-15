<?php

namespace App\Documents;

use App\Actions\Payroll\PayrollCalculator;
use App\Attendance\MonthlySummary;
use App\Models\Employee;
use App\Models\SalaryAdjustment;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * One employee's pay for one month. The money comes from PayrollCalculator, the
 * same code the month-close uses, so a payslip can never disagree with the
 * salary due. The attendance block is information only: it never changes the
 * amount.
 */
class Payslip
{
    public function __construct(
        private readonly PayrollCalculator $payroll,
        private readonly MonthlySummary $attendance,
    ) {}

    /**
     * @return array{
     *     employee: Employee,
     *     month: CarbonImmutable,
     *     figures: array{salary: int, bonuses: int, deductions: int, advances: int, net: int},
     *     adjustments: Collection<int, SalaryAdjustment>,
     *     attendance: array<string, int>,
     * }|null Null when the employee has no salary in force that month.
     */
    public function build(Employee $employee, CarbonImmutable $month): ?array
    {
        $first = $month->startOfMonth();
        $figures = $this->payroll->forEmployee($employee, $first);

        if ($figures === null) {
            return null;
        }

        return [
            'employee' => $employee,
            'month' => $first,
            'figures' => $figures,
            'adjustments' => SalaryAdjustment::query()
                ->where('employee_id', $employee->id)
                ->whereDate('month', $first->toDateString())
                ->orderBy('type')
                ->orderBy('id')
                ->get(),
            'attendance' => $this->attendance->for($employee, $first),
        ];
    }
}
