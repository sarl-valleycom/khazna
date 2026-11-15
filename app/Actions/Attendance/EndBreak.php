<?php

namespace App\Actions\Attendance;

use App\Actions\Attendance\Concerns\LocksEmployee;
use App\Attendance\AttendanceException;
use App\Models\AttendanceBreak;
use App\Models\AttendanceSession;
use App\Models\Employee;
use App\Support\AlgerianDate;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class EndBreak
{
    use LocksEmployee;

    public function handle(Employee $employee, ?CarbonImmutable $at = null): AttendanceBreak
    {
        $now = ($at ?? AlgerianDate::now())->utc();

        return DB::transaction(function () use ($employee, $now): AttendanceBreak {
            $locked = $this->lockEmployee($employee->id);

            $session = AttendanceSession::query()
                ->where('employee_id', $locked->id)
                ->open()
                ->lockForUpdate()
                ->first();

            if ($session === null) {
                throw AttendanceException::noOpenSession();
            }

            $break = $session->breaks()->open()->lockForUpdate()->first();

            if ($break === null) {
                throw AttendanceException::notAway();
            }

            $break->update(['ended_at' => $now]);

            return $break->refresh();
        });
    }
}
