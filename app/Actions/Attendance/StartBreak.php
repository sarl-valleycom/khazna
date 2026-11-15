<?php

namespace App\Actions\Attendance;

use App\Actions\Attendance\Concerns\LocksEmployee;
use App\Attendance\AttendanceException;
use App\Enums\AttendanceSource;
use App\Models\AttendanceBreak;
use App\Models\AttendanceSession;
use App\Models\Employee;
use App\Support\AlgerianDate;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class StartBreak
{
    use LocksEmployee;

    public function handle(
        Employee $employee,
        string $reason,
        AttendanceSource $source = AttendanceSource::Discord,
        ?CarbonImmutable $at = null,
    ): AttendanceBreak {
        $now = ($at ?? AlgerianDate::now())->utc();
        $reason = trim($reason);

        if ($reason === '') {
            throw AttendanceException::reasonRequired();
        }

        return DB::transaction(function () use ($employee, $reason, $now): AttendanceBreak {
            $locked = $this->lockEmployee($employee->id);

            $session = AttendanceSession::query()
                ->where('employee_id', $locked->id)
                ->open()
                ->lockForUpdate()
                ->first();

            if ($session === null) {
                throw AttendanceException::noOpenSession();
            }

            $openBreak = $session->breaks()->open()->lockForUpdate()->first();

            if ($openBreak !== null) {
                throw AttendanceException::alreadyAway($openBreak->started_at);
            }

            try {
                return AttendanceBreak::query()->create([
                    'session_id' => $session->id,
                    'started_at' => $now,
                    'reason' => mb_substr($reason, 0, 500),
                    'paid' => false,
                ]);
            } catch (UniqueConstraintViolationException) {
                $existing = $session->breaks()->open()->lockForUpdate()->first();

                if ($existing === null) {
                    throw AttendanceException::alreadyAway($now);
                }

                throw AttendanceException::alreadyAway($existing->started_at);
            }
        });
    }
}
