<?php

namespace App\Discord\Handlers\Attendance\Concerns;

use App\Attendance\WorkedTime;
use App\Jobs\SendAttendanceEvent;
use App\Models\AttendanceSetting;
use App\Models\Employee;
use App\Support\AlgerianDate;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;

trait SpeaksAttendance
{
    protected function time(CarbonImmutable $at): string
    {
        return $at->setTimezone(AlgerianDate::TZ)->format('H:i');
    }

    protected function todayTotal(Employee $employee, CarbonImmutable $at): string
    {
        return WorkedTime::format(WorkedTime::todayMinutes(
            $employee,
            $at,
            AttendanceSetting::current()->paid_break_minutes,
        ));
    }

    /**
     * @param  array<string, string>  $parameters
     */
    protected function postEvent(Employee $employee, string $translationKey, array $parameters = []): void
    {
        $channelId = (string) ($employee->office()->value('attendance_channel_id') ?? '');

        if ($channelId === '') {
            Log::warning('Attendance event not posted: the office has no attendance channel.', [
                'employee_id' => $employee->id,
                'event' => $translationKey,
            ]);

            return;
        }

        $parameters['mention'] = '<@'.$employee->discord_user_id.'>';

        SendAttendanceEvent::dispatch(
            $channelId,
            (string) __($translationKey, $parameters),
        );
    }
}
