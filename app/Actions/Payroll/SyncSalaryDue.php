<?php

namespace App\Actions\Payroll;

use App\Actions\Payments\RecalculateDue;
use App\Enums\DueStatus;
use App\Models\Due;
use App\Models\Employee;
use App\Support\AuditContext;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

/**
 * Keeps a month's salary due equal to the month's adjustments (FR-SAL-06).
 *
 * While nothing has been paid on the due, it just follows. Once money has been
 * paid, changing it is a correction that needs a reason: it is written to the
 * audit log and the due's status is recalculated (a salary overpaid by the change
 * shows as a credit; one now short becomes part-paid).
 */
class SyncSalaryDue
{
    public function __construct(
        private readonly PayrollCalculator $calculator,
        private readonly RecalculateDue $recalculate,
    ) {}

    public function handle(Employee $employee, CarbonImmutable $month, ?string $correctionReason = null): ?Due
    {
        $due = Due::query()
            ->where('employee_id', $employee->id)
            ->whereDate('period', $month->startOfMonth()->toDateString())
            ->lockForUpdate()
            ->first();

        if ($due === null || $due->status === DueStatus::Canceled) {
            return $due;
        }

        $line = $this->calculator->forEmployee($employee, $month);

        if ($line === null) {
            return $due;
        }

        // Nothing is owed once advances and deductions cover the salary: never a negative due.
        $net = max(0, $line['net']);

        if ($net === $due->amount_due_cents) {
            return $due;
        }

        $reason = $correctionReason !== null ? trim($correctionReason) : '';

        if ($due->amount_paid_cents > 0 && $reason === '') {
            throw ValidationException::withMessages([
                'correction_reason' => __('This salary is already paid, at least in part. Give a reason to correct it.'),
            ]);
        }

        // The status recalculation is part of the same correction, so it carries the same reason.
        app(AuditContext::class)->withReason($reason !== '' ? $reason : __('Salary adjusted'), function () use ($due, $net): void {
            // Nothing paid and nothing owed: the due is withdrawn rather than left at zero forever.
            if ($net === 0 && $due->amount_paid_cents === 0) {
                $due->update([
                    'amount_due_cents' => 0,
                    'status' => DueStatus::Canceled,
                    'canceled_reason' => __('Advances and deductions cover the whole salary.'),
                ]);

                return;
            }

            $due->update(['amount_due_cents' => $net]);
            $this->recalculate->handle($due);
        });

        return $due->refresh();
    }
}
