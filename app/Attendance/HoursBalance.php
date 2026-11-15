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
 */
class HoursBalance
{
    public function __construct(private readonly WorkCalendar $calendar) {}

    /**
     * @return array{today: array<string, int>, week: array<string, int>, month: array<string, int>}
     */
    public function for(Employee $employee, ?CarbonImmutable $at = null): array
    {
        $now = ($at ?? AlgerianDate::now())->utc();
        $today = AlgerianDate::toAlgiers($now)->startOfDay();
        $allowance = AttendanceSetting::current()->paid_break_minutes;

        $periods = [
            'today' => $today,
            'week' => AlgerianDate::startOfWeek($today)->startOfDay(),
            'month' => AlgerianDate::startOfMonth($today)->startOfDay(),
        ];

        $summary = [];

        foreach ($periods as $key => $start) {
            $summary[$key] = $this->period($employee, $start, $today, $now, $allowance);
        }

        return $summary;
    }

    /**
     * @return array<string, int>
     *                            expected: target minutes from the start of the period through today.
     *                            worked: minutes worked in the same days.
     *                            remaining: target minutes still to work (never below zero).
     *                            extra: minutes worked beyond the target (never below zero).
     *                            behind_before_today: minutes missing from the days before today.
     */
    private function period(
        Employee $employee,
        CarbonImmutable $start,
        CarbonImmutable $today,
        CarbonImmutable $now,
        int $allowance,
    ): array {
        $expected = $this->calendar->expectedMinutes($employee, $start, $today);
        $worked = WorkedTime::rangeMinutes(
            $employee,
            $start->utc(),
            $today->endOfDay()->utc(),
            $now,
            $allowance,
        );

        $behind = 0;

        if ($start->lt($today)) {
            $yesterday = $today->subDay();
            $expectedBefore = $this->calendar->expectedMinutes($employee, $start, $yesterday);
            $workedBefore = WorkedTime::rangeMinutes(
                $employee,
                $start->utc(),
                $yesterday->endOfDay()->utc(),
                $now,
                $allowance,
            );
            $behind = max(0, $expectedBefore - $workedBefore);
        }

        return [
            'expected' => $expected,
            'worked' => $worked,
            'remaining' => max(0, $expected - $worked),
            'extra' => max(0, $worked - $expected),
            'behind_before_today' => $behind,
        ];
    }
}
