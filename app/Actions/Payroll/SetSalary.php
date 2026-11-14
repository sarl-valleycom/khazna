<?php

namespace App\Actions\Payroll;

use App\Enums\DueKind;
use App\Models\Due;
use App\Models\Employee;
use App\Models\EmployeeSalary;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

class SetSalary
{
    /**
     * Record "this much a month from this date". Raises are only ever added: a date
     * that already has one is refused, so a closed month can never be rewritten.
     */
    public function handle(Employee $employee, int $amountCents, string $effectiveFrom, ?User $by): EmployeeSalary
    {
        $exists = EmployeeSalary::query()
            ->where('employee_id', $employee->id)
            ->whereDate('effective_from', $effectiveFrom)
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'effective_from' => __('This employee already has a salary starting on that date. Choose a later date for a raise.'),
            ]);
        }

        // A closed month has its salary due. A raise dated inside it (or earlier) would change the
        // salary in force for that month behind the due's back, so it has to start after it.
        $closedThrough = Due::query()
            ->where('employee_id', $employee->id)
            ->where('kind', DueKind::Salary)
            ->whereDate('period', '>=', CarbonImmutable::parse($effectiveFrom)->startOfMonth()->toDateString())
            ->orderByDesc('period')
            ->value('period');

        if ($closedThrough !== null) {
            throw ValidationException::withMessages([
                'effective_from' => __(':month is already closed. A raise has to start after the last closed month.', [
                    'month' => CarbonImmutable::parse((string) $closedThrough)->format('F Y'),
                ]),
            ]);
        }

        return EmployeeSalary::query()->create([
            'employee_id' => $employee->id,
            'amount_cents' => $amountCents,
            'effective_from' => $effectiveFrom,
            'created_by' => $by?->id,
        ]);
    }
}
