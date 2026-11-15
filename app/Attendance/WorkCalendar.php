<?php

namespace App\Attendance;

use App\Enums\LeaveStatus;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\LeaveRequest;
use App\Support\AlgerianDate;
use Carbon\CarbonInterface;

/**
 * Answers the calendar questions shared by reminders and auto-close: is this a
 * working day for this employee, and how many minutes were expected in a
 * range? Weekends, public holidays and approved leave are excluded.
 */
class WorkCalendar
{
    public function isWorkingDay(Employee $employee, CarbonInterface|string $date): bool
    {
        $day = AlgerianDate::toAlgiers($date)->startOfDay();

        if (! $employee->worksOn($day)) {
            return false;
        }

        if ($this->isHoliday($day)) {
            return false;
        }

        return ! $this->isOnApprovedLeave($employee, $day);
    }

    public function isHoliday(CarbonInterface|string $date): bool
    {
        return Holiday::query()
            ->whereDate('date', AlgerianDate::toAlgiers($date)->toDateString())
            ->exists();
    }

    public function isOnApprovedLeave(Employee $employee, CarbonInterface|string $date): bool
    {
        $day = AlgerianDate::toAlgiers($date)->toDateString();

        return LeaveRequest::query()
            ->where('employee_id', $employee->id)
            ->where('status', LeaveStatus::Approved)
            ->whereDate('from', '<=', $day)
            ->whereDate('to', '>=', $day)
            ->exists();
    }

    public function expectedMinutes(Employee $employee, CarbonInterface|string $from, CarbonInterface|string $to): int
    {
        $start = AlgerianDate::toAlgiers($from)->startOfDay();
        $end = AlgerianDate::toAlgiers($to)->startOfDay();

        if ($end->lt($start)) {
            return 0;
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

        $minutes = 0;

        for ($day = $start; $day->lte($end); $day = $day->addDay()) {
            $date = $day->toDateString();

            if (! $employee->worksOn($day) || in_array($date, $holidays, true)) {
                continue;
            }

            $onLeave = $leaves->contains(fn (LeaveRequest $leave): bool => $leave->coversOn($date));

            if (! $onLeave) {
                $minutes += $employee->expected_minutes_per_day;
            }
        }

        return $minutes;
    }
}
