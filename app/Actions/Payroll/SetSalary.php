<?php

namespace App\Actions\Payroll;

use App\Models\Employee;
use App\Models\EmployeeSalary;
use App\Models\User;
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

        return EmployeeSalary::query()->create([
            'employee_id' => $employee->id,
            'amount_cents' => $amountCents,
            'effective_from' => $effectiveFrom,
            'created_by' => $by?->id,
        ]);
    }
}
