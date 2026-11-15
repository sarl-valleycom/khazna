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
     * Today's worked minutes for many employees with two queries in total
     * (sessions and their breaks), instead of two per employee.
     *
     * @param  array<int, int>  $employeeIds
     * @return array<int, int> employee id => minutes, with 0 for employees who have no session today
     */
    public static function todayMinutesForEmployees(
        array $employeeIds,
        ?CarbonImmutable $at = null,
        ?int $paidBreakAllowanceMinutes = null,
    ): array {
        $now = ($at ?? AlgerianDate::now())->utc();
        $date = $now->setTimezone(AlgerianDate::TZ)->toDateString();
        [$from, $to] = AlgerianDate::dayRangeUtc($date);

        $byEmployee = AttendanceSession::query()
            ->whereIn('employee_id', $employeeIds)
            ->whereBetween('started_at', [$from->utc(), $to->utc()])
            ->with('breaks')
            ->get()
            ->groupBy('employee_id');

        $minutes = [];

        foreach ($employeeIds as $employeeId) {
            $minutes[$employeeId] = self::minutesForSessions(
                $byEmployee->get($employeeId, []),
                $now,
                $paidBreakAllowanceMinutes,
            );
        }

        return $minutes;
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

        return self::minutesForSessions($sessions, $now, $paidBreakAllowanceMinutes);
    }

    /**
     * Worked minutes of each session, with the paid break allowance spent
     * session by session through each Algiers day, in start order. The pieces
     * add up to exactly the day's total from minutesForSessions(), so a list of
     * sessions and the daily total never disagree.
     *
     * @param  iterable<AttendanceSession>  $sessions
     * @return array<int, int> session id => minutes
     */
    public static function minutesPerSession(
        iterable $sessions,
        ?CarbonImmutable $at = null,
        ?int $paidBreakAllowanceMinutes = null,
    ): array {
        $now = ($at ?? AlgerianDate::now())->utc();
        $allowance = $paidBreakAllowanceMinutes
            ?? AttendanceSetting::current()->paid_break_minutes;

        $ordered = collect($sessions)->sortBy(fn (AttendanceSession $session): int => $session->started_at->getTimestamp());

        /** @var array<string, int> $left allowance seconds still unspent, per Algiers day */
        $left = [];
        $minutes = [];

        foreach ($ordered as $session) {
            $end = self::effectiveEnd($session, $now);
            $day = $session->started_at->setTimezone(AlgerianDate::TZ)->toDateString();
            $left[$day] ??= max(0, $allowance) * 60;

            if ($end->lte($session->started_at)) {
                $minutes[$session->id] = 0;

                continue;
            }

            $gross = $end->getTimestamp() - $session->started_at->getTimestamp();
            $breaks = self::breakSeconds($session, $end);
            $credit = min($breaks, $left[$day]);
            $left[$day] -= $credit;

            $minutes[$session->id] = intdiv(max(0, $gross - ($breaks - $credit)), 60);
        }

        return $minutes;
    }

    /**
     * Worked minutes for one employee's sessions. The paid break allowance is a
     * per-day rule (BR-02): it is subtracted from each Algiers day's break time
     * once, never once for the whole range.
     *
     * @param  iterable<AttendanceSession>  $sessions
     */
    public static function minutesForSessions(
        iterable $sessions,
        ?CarbonImmutable $at = null,
        ?int $paidBreakAllowanceMinutes = null,
    ): int {
        $now = ($at ?? AlgerianDate::now())->utc();
        $allowance = $paidBreakAllowanceMinutes
            ?? AttendanceSetting::current()->paid_break_minutes;
        $paidBreakSeconds = max(0, $allowance) * 60;

        /** @var array<string, array{gross: int, breaks: int}> $days */
        $days = [];

        foreach ($sessions as $session) {
            $end = self::effectiveEnd($session, $now);

            if ($end->lte($session->started_at)) {
                continue;
            }

            $day = $session->started_at->setTimezone(AlgerianDate::TZ)->toDateString();

            $days[$day] ??= ['gross' => 0, 'breaks' => 0];
            $days[$day]['gross'] += $end->getTimestamp() - $session->started_at->getTimestamp();
            $days[$day]['breaks'] += self::breakSeconds($session, $end);
        }

        $seconds = 0;

        foreach ($days as $day) {
            $unpaidBreakSeconds = max(0, $day['breaks'] - $paidBreakSeconds);
            $seconds += max(0, $day['gross'] - $unpaidBreakSeconds);
        }

        return intdiv($seconds, 60);
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
