<?php

namespace App\Attendance;

use App\Models\AttendanceSession;
use App\Models\AttendanceSetting;
use App\Models\Employee;
use App\Support\AlgerianDate;
use Carbon\CarbonImmutable;

/**
 * Worked time is always computed from real timestamps, never stored as a
 * mutable total. A session belongs to the Algiers day it started on, even when
 * it crosses midnight (BR-04).
 */
final class WorkedTime
{
    public static function sessionMinutes(AttendanceSession $session, ?CarbonImmutable $until = null): int
    {
        $end = self::effectiveEnd($session, $until);

        if ($end->lte($session->started_at)) {
            return 0;
        }

        $seconds = $end->getTimestamp() - $session->started_at->getTimestamp();

        return intdiv(max(0, $seconds - self::breakSeconds($session, $end)), 60);
    }

    public static function todayMinutes(
        Employee $employee,
        ?CarbonImmutable $at = null,
        ?int $paidBreakAllowanceMinutes = null,
    ): int {
        $now = ($at ?? AlgerianDate::now())->utc();
        $date = $now->setTimezone(AlgerianDate::TZ)->toDateString();
        [$from, $to] = AlgerianDate::dayRangeUtc($date);

        return self::rangeMinutes($employee, $from, $to, $now, $paidBreakAllowanceMinutes);
    }

    /**
     * Worked minutes for sessions that started inside the UTC range. A session
     * belongs to the Algiers day it started on, even when it crosses midnight.
     */
    public static function rangeMinutes(
        Employee $employee,
        CarbonImmutable $from,
        CarbonImmutable $to,
        ?CarbonImmutable $at = null,
        ?int $paidBreakAllowanceMinutes = null,
    ): int {
        $now = ($at ?? AlgerianDate::now())->utc();

        $sessions = $employee->attendanceSessions()
            ->whereBetween('started_at', [$from->utc(), $to->utc()])
            ->with('breaks')
            ->get();

        $grossSeconds = 0;
        $breakSeconds = 0;

        foreach ($sessions as $session) {
            $end = self::effectiveEnd($session, $now);

            if ($end->lte($session->started_at)) {
                continue;
            }

            $grossSeconds += $end->getTimestamp() - $session->started_at->getTimestamp();
            $breakSeconds += self::breakSeconds($session, $end);
        }

        $allowance = $paidBreakAllowanceMinutes
            ?? AttendanceSetting::current()->paid_break_minutes;

        $paidBreakSeconds = max(0, $allowance) * 60;
        $unpaidBreakSeconds = max(0, $breakSeconds - $paidBreakSeconds);

        return intdiv(max(0, $grossSeconds - $unpaidBreakSeconds), 60);
    }

    public static function format(int $minutes): string
    {
        $hours = intdiv(max(0, $minutes), 60);
        $rest = max(0, $minutes) % 60;

        return $hours > 0 ? "{$hours}h ".str_pad((string) $rest, 2, '0', STR_PAD_LEFT).'m' : "{$rest}m";
    }

    private static function effectiveEnd(AttendanceSession $session, ?CarbonImmutable $until): CarbonImmutable
    {
        if ($until === null) {
            return $session->ended_at ?? AlgerianDate::now()->utc();
        }

        $until = $until->utc();

        return $session->ended_at === null
            ? $until
            : ($session->ended_at->lt($until) ? $session->ended_at : $until);
    }

    private static function breakSeconds(AttendanceSession $session, CarbonImmutable $end): int
    {
        $breaks = $session->relationLoaded('breaks')
            ? $session->breaks
            : $session->breaks()->get();

        $seconds = 0;

        foreach ($breaks as $break) {
            $from = $break->started_at->gt($session->started_at)
                ? $break->started_at
                : $session->started_at;
            $to = $break->ended_at === null
                ? $end
                : ($break->ended_at->lt($end) ? $break->ended_at : $end);

            if ($to->gt($from)) {
                $seconds += $to->getTimestamp() - $from->getTimestamp();
            }
        }

        return $seconds;
    }
}
