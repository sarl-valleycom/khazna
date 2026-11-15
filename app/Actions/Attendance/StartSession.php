<?php

namespace App\Actions\Attendance;

use App\Actions\Attendance\Concerns\LocksEmployee;
use App\Attendance\AttendanceException;
use App\Attendance\StartedSession;
use App\Enums\AttendanceReviewStatus;
use App\Enums\AttendanceSource;
use App\Enums\AttendanceStatus;
use App\Models\AttendanceSession;
use App\Models\Employee;
use App\Support\AlgerianDate;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Starts a session, or ends an open break when the employee is away. The
 * unique `open_employee_id` index is the final guard against a double click.
 */
class StartSession
{
    use LocksEmployee;

    public function handle(
        Employee $employee,
        AttendanceSource $source = AttendanceSource::Web,
        ?CarbonImmutable $at = null,
    ): StartedSession {
        $now = ($at ?? AlgerianDate::now())->utc();

        return DB::transaction(function () use ($employee, $source, $now): StartedSession {
            $locked = $this->lockEmployee($employee->id);

            if (! $locked->isActive()) {
                throw AttendanceException::inactive();
            }

            $open = AttendanceSession::query()
                ->where('employee_id', $locked->id)
                ->open()
                ->lockForUpdate()
                ->first();

            if ($open !== null) {
                $break = $open->breaks()->open()->lockForUpdate()->first();

                if ($break === null) {
                    throw AttendanceException::alreadyCheckedIn($open->started_at);
                }

                $break->update(['ended_at' => $now]);

                return new StartedSession($open->refresh(), resumed: true);
            }

            try {
                $session = AttendanceSession::query()->create([
                    'employee_id' => $locked->id,
                    'started_at' => $now,
                    'status' => AttendanceStatus::Open,
                    'review_status' => AttendanceReviewStatus::Ok,
                    'source' => $source,
                ]);
            } catch (UniqueConstraintViolationException) {
                $existing = AttendanceSession::query()
                    ->where('employee_id', $locked->id)
                    ->open()
                    ->lockForUpdate()
                    ->first();

                if ($existing === null) {
                    throw AttendanceException::alreadyCheckedIn($now);
                }

                throw AttendanceException::alreadyCheckedIn($existing->started_at);
            }

            return new StartedSession($session, resumed: false);
        });
    }
}
