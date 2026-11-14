<?php

namespace App\Data;

use App\Models\Employee;
use App\Models\EmployeeSalary;
use App\Support\AlgerianDate;
use App\Support\Money;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Only ever built for a user who passed the `view-salaries` gate.
 */
#[TypeScript]
class EmployeeWithSalaryData extends Data
{
    /**
     * @param  array<int, SalaryHistoryData>  $salaryHistory
     */
    public function __construct(
        public EmployeeData $employee,
        public ?int $currentSalaryCents,
        public ?string $currentSalaryFormatted,
        public array $salaryHistory,
    ) {}

    /**
     * @param  int|null  $currentSalaryCents  Pass a precomputed value to avoid a query per row in a list.
     */
    public static function fromEmployee(Employee $employee, bool $withHistory = false, ?int $currentSalaryCents = null): self
    {
        $current = $currentSalaryCents ?? $employee->salaryOn(AlgerianDate::today());

        return new self(
            employee: EmployeeData::fromEmployee($employee),
            currentSalaryCents: $current,
            currentSalaryFormatted: $current === null ? null : Money::fromCents($current)->format(),
            salaryHistory: $withHistory
                ? $employee->salaries()
                    ->orderByDesc('effective_from')
                    ->get()
                    ->map(fn (EmployeeSalary $salary): SalaryHistoryData => SalaryHistoryData::fromSalary($salary))
                    ->values()
                    ->all()
                : [],
        );
    }
}
