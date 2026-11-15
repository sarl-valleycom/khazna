<?php

namespace App\Attendance;

use App\Models\AttendanceSetting;
use App\Models\Employee;
use App\Support\AlgerianDate;
use Carbon\CarbonImmutable;

/**
 * Worked time against the employee's own daily target, for today, this week
 * and this month. It is information for the employee and the owner; salary does
 * not depend on it. The target comes from each employee's expected minutes per
 * day and work days, minus holidays and approved leave.
 *
 * Everything is worked out from one set of sessions and one calendar, day by
 * day, so the page costs the same few queries whatever the period.
 */
class HoursBalance
{
    public function __construct(private readonly WorkCalendar $calendar) {}

    /**
     * Per period: expected (target through today), worked, remaining (target
     * still to work, never below zero), extra (worked beyond the target, never
     * below zero) and behind_before_today (what is missing from earlier days).
     *
     * @return array{today: array<string, int>, week: array<string, int>, month: array<string, int>}
     */
    public function for(Employee $employee, ?CarbonImmutable $at = null): array
    {
        $now = ($at ?? AlgerianDate::now())->utc();
        $today = AlgerianDate::toAlgiers($now)->startOfDay();
        $todayKey = $today->toDateString();

        $starts = [
            'today' => $today,
            'week' => AlgerianDate::startOfWeek($today)->startOfDay(),
            'month' => AlgerianDate::startOfMonth($today)->startOfDay(),
        ];
        $from = $starts['week']->lt($starts['month']) ? $starts['week'] : $starts['month'];

        $sessions = $employee->attendanceSessions()
            ->whereBetween('started_at', [$from->utc(), $today->endOfDay()->utc()])
            ->with('breaks')
            ->get();

        $expectedByDay = $this->calendar->expectedByDay($employee, $from, $today);
        $workedByDay = WorkedTime::minutesByDay($sessions, $now, AttendanceSetting::current()->paid_break_minutes);

        $summary = [];

        foreach ($starts as $key => $start) {
            $expected = $worked = $expectedBefore = $workedBefore = 0;

            foreach ($expectedByDay as $date => $minutes) {
                if ($date < $start->toDateString()) {
                    continue;
                }

                $expected += $minutes;
                $worked += $workedByDay[$date] ?? 0;

                if ($date < $todayKey) {
                    $expectedBefore += $minutes;
                    $workedBefore += $workedByDay[$date] ?? 0;
                }
            }

            $summary[$key] = [
                'expected' => $expected,
                'worked' => $worked,
                'remaining' => max(0, $expected - $worked),
                'extra' => max(0, $worked - $expected),
                'behind_before_today' => max(0, $expectedBefore - $workedBefore),
            ];
        }

        return $summary;
    }
}
