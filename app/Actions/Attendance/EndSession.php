<?php

namespace App\Actions\Attendance;

use App\Actions\Attendance\Concerns\LocksEmployee;
use App\Attendance\AttendanceException;
use App\Enums\AttendanceStatus;
use App\Models\AttendanceSession;
use App\Models\Employee;
use App\Models\User;
use App\Support\AlgerianDate;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class EndSession
{
    use LocksEmployee;

    public function handle(
        Employee $employee,
        ?CarbonImmutable $at = null,
        ?User $closedBy = null,
    ): AttendanceSession {
        $now = ($at ?? AlgerianDate::now())->utc();

        return DB::transaction(function () use ($employee, $now, $closedBy): AttendanceSession {
            $locked = $this->lockEmployee($employee->id);

            $session = AttendanceSession::query()
                ->where('employee_id', $locked->id)
                ->open()
                ->lockForUpdate()
                ->first();

            if ($session === null) {
                throw AttendanceException::noOpenSession();
            }

            $session->breaks()
                ->open()
                ->lockForUpdate()
                ->get()
                ->each(fn ($break) => $break->update(['ended_at' => $now]));

            $session->update([
                'ended_at' => $now,
                'status' => AttendanceStatus::Closed,
                'closed_by' => $closedBy?->id,
            ]);

            return $session->refresh()->load('breaks');
        });
    }
}
