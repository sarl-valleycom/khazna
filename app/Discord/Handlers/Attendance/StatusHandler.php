<?php

namespace App\Discord\Handlers\Attendance;

use App\Attendance\AttendanceException;
use App\Attendance\WorkedTime;
use App\Discord\Handlers\Attendance\Concerns\FindsEmployee;
use App\Discord\Handlers\Attendance\Concerns\SpeaksAttendance;
use App\Discord\Interaction;
use App\Discord\InteractionHandler;
use App\Discord\InteractionResponse;
use App\Models\AttendanceSession;
use App\Models\AttendanceSetting;
use App\Models\Employee;
use App\Support\AlgerianDate;

class StatusHandler implements InteractionHandler
{
    use FindsEmployee, SpeaksAttendance;

    public function handle(Interaction $interaction): InteractionResponse
    {
        $userId = $interaction->userId();

        if ($userId === null) {
            throw AttendanceException::notLinked();
        }

        $employee = $this->employeeFor($userId);
        $now = AlgerianDate::now();
        $total = WorkedTime::format(WorkedTime::todayMinutes(
            $employee,
            $now,
            AttendanceSetting::current()->paid_break_minutes,
        ));

        $session = $this->openSession($employee);

        if ($session === null) {
            return InteractionResponse::message(
                (string) __('discord.status.offline', ['total' => $total]),
                ephemeral: true,
            );
        }

        $break = $session->breaks->first(fn ($break): bool => $break->ended_at === null);

        if ($break !== null) {
            return InteractionResponse::message(
                (string) __('discord.status.away', [
                    'time' => $this->time($break->started_at),
                    'total' => $total,
                ]),
                ephemeral: true,
            );
        }

        return InteractionResponse::message(
            (string) __('discord.status.working', [
                'time' => $this->time($session->started_at),
                'total' => $total,
            ]),
            ephemeral: true,
        );
    }

    private function openSession(Employee $employee): ?AttendanceSession
    {
        return $employee->attendanceSessions()
            ->open()
            ->with('breaks')
            ->latest('started_at')
            ->first();
    }
}
