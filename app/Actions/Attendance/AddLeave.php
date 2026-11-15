<?php

namespace App\Actions\Attendance;

use App\Enums\LeaveStatus;
use App\Enums\LeaveType;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Support\AlgerianDate;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The owner records leave for an employee (an illness reported by phone, a
 * planned holiday). It is approved at once: nobody else decides it. The type is
 * a label; it never changes pay.
 */
class AddLeave
{
    public function handle(
        Employee $employee,
        LeaveType $type,
        string $from,
        string $to,
        ?string $reason,
        User $actor,
    ): LeaveRequest {
        return DB::transaction(function () use ($employee, $type, $from, $to, $reason, $actor): LeaveRequest {
            $locked = Employee::query()->whereKey($employee->id)->lockForUpdate()->firstOrFail();

            if (! $locked->isActive()) {
                throw ValidationException::withMessages(['employee_id' => __('This employee is deactivated.')]);
            }

            $overlaps = LeaveRequest::query()
                ->where('employee_id', $locked->id)
                ->whereIn('status', [LeaveStatus::Pending, LeaveStatus::Approved])
                ->whereDate('from', '<=', $to)
                ->whereDate('to', '>=', $from)
                ->exists();

            if ($overlaps) {
                throw ValidationException::withMessages([
                    'from' => __('This employee already has leave on some of these days.'),
                ]);
            }

            $reason = $reason === null ? null : trim($reason);

            return LeaveRequest::query()->create([
                'employee_id' => $locked->id,
                'type' => $type,
                'from' => $from,
                'to' => $to,
                'reason' => $reason === '' ? null : $reason,
                'status' => LeaveStatus::Approved,
                'decided_by' => $actor->id,
                'decided_at' => AlgerianDate::now()->utc(),
            ]);
        });
    }
}
