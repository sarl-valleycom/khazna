<?php

namespace App\Actions\Attendance;

use App\Actions\Attendance\Concerns\LocksEmployee;
use App\Enums\AttendanceReviewStatus;
use App\Enums\AttendanceStatus;
use App\Models\AttendanceSession;
use App\Models\Employee;
use App\Support\AlgerianDate;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class AutoCloseSession
{
    use LocksEmployee;

    public function handle(AttendanceSession $session, ?CarbonImmutable $now = null): AttendanceSession
    {
        $now = ($now ?? AlgerianDate::now())->utc();

        return DB::transaction(function () use ($session, $now): AttendanceSession {
            $employee = $this->lockEmployee($session->employee_id);

            $locked = AttendanceSession::query()
                ->whereKey($session->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $locked->isOpen()) {
                return $locked;
            }

            $end = $this->closingTime($employee, $locked, $now);

            $locked->breaks()
                ->open()
                ->lockForUpdate()
                ->get()
                ->each(function ($break) use ($end): void {
                    $break->update([
                        'ended_at' => $end->lt($break->started_at) ? $break->started_at : $end,
                    ]);
                });

            $locked->update([
                'ended_at' => $end,
                'status' => AttendanceStatus::AutoClosed,
                'review_status' => AttendanceReviewStatus::NeedsReview,
            ]);

            return $locked->refresh()->load('breaks');
        });
    }

    private function closingTime(Employee $employee, AttendanceSession $session, CarbonImmutable $now): CarbonImmutable
    {
        $startedLocal = $session->started_at->setTimezone(AlgerianDate::TZ);

        if ($employee->schedule_end !== null) {
            $endLocal = $startedLocal->setTimeFromTimeString($employee->schedule_end);

            if ($employee->schedule_start !== null && $employee->schedule_end <= $employee->schedule_start) {
                $endLocal = $endLocal->addDay();
            }
        } else {
            $endLocal = $startedLocal->addMinutes(max(1, $employee->expected_minutes_per_day));
        }

        $end = $endLocal->utc();

        if ($session->started_at->gte($end)) {
            return $now;
        }

        return $end->lt($now) ? $end : $now;
    }
}
