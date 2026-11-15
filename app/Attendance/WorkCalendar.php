<?php

namespace App\Attendance;

use App\Enums\LeaveStatus;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\LeaveRequest;
use App\Support\AlgerianDate;
use Carbon\CarbonInterface;

/**
 * How many minutes an employee is expected to work, day by day. A day counts
 * only when it is one of the employee's work days, not a public holiday and
 * not covered by approved leave.
 */
class WorkCalendar
{
    /**
     * Expected minutes for each day from the first to the last date, in two
     * queries (holidays and leave) however long the range is.
     *
     * @return array<string, int> date (Y-m-d) => minutes, with 0 for a day off
     */
    public function expectedByDay(Employee $employee, CarbonInterface|string $from, CarbonInterface|string $to): array
    {
        $start = AlgerianDate::toAlgiers($from)->startOfDay();
        $end = AlgerianDate::toAlgiers($to)->startOfDay();

        if ($end->lt($start)) {
            return [];
        }

        $holidays = Holiday::query()
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->pluck('date')
            ->map(fn ($date): string => $date instanceof CarbonInterface ? $date->toDateString() : (string) $date)
            ->all();

        $leaves = LeaveRequest::query()
            ->where('employee_id', $employee->id)
            ->where('status', LeaveStatus::Approved)
            ->whereDate('from', '<=', $end->toDateString())
            ->whereDate('to', '>=', $start->toDateString())
            ->get();

        $minutes = [];

        for ($day = $start; $day->lte($end); $day = $day->addDay()) {
            $date = $day->toDateString();
            $off = ! $employee->worksOn($day)
                || in_array($date, $holidays, true)
                || $leaves->contains(fn (LeaveRequest $leave): bool => $leave->coversOn($date));

            $minutes[$date] = $off ? 0 : $employee->expected_minutes_per_day;
        }

        return $minutes;
    }
}
