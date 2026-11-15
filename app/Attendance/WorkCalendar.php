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
 * only when it is one of the employee's work days, between the day they were
 * hired and the day they left, not a public holiday and not covered by approved
 * leave.
 */
class WorkCalendar
{
    /**
     * Load the holidays and approved leave for a range, for one or many employees.
     *
     * @param  array<int, int>  $employeeIds
     */
    public function window(CarbonInterface|string $from, CarbonInterface|string $to, array $employeeIds): CalendarWindow
    {
        $start = AlgerianDate::toAlgiers($from)->toDateString();
        $end = AlgerianDate::toAlgiers($to)->toDateString();

        $holidays = Holiday::query()
            ->whereBetween('date', [$start, $end])
            ->pluck('date')
            ->mapWithKeys(fn ($date): array => [($date instanceof CarbonInterface ? $date->toDateString() : (string) $date) => true])
            ->all();

        $leaves = LeaveRequest::query()
            ->whereIn('employee_id', $employeeIds)
            ->where('status', LeaveStatus::Approved)
            ->whereDate('from', '<=', $end)
            ->whereDate('to', '>=', $start)
            ->get()
            ->groupBy('employee_id')
            ->all();

        return new CalendarWindow($holidays, $leaves);
    }

    /**
     * Each day from the first to the last date: the minutes expected, and why
     * the day is off when it is a work day that does not count ('holiday' or
     * 'leave'). A weekend or a day outside the employment is simply 0, with no
     * reason.
     *
     * @return array<string, array{expected: int, off: 'holiday'|'leave'|null}>
     */
    public function days(
        Employee $employee,
        CarbonInterface|string $from,
        CarbonInterface|string $to,
        ?CalendarWindow $window = null,
    ): array {
        $start = AlgerianDate::toAlgiers($from)->startOfDay();
        $end = AlgerianDate::toAlgiers($to)->startOfDay();

        if ($end->lt($start)) {
            return [];
        }

        $window ??= $this->window($start, $end, [$employee->id]);
        $hired = $employee->hire_date?->toDateString();
        $left = $employee->left_on?->toDateString();

        $days = [];

        for ($day = $start; $day->lte($end); $day = $day->addDay()) {
            $date = $day->toDateString();

            $employed = ($hired === null || $date >= $hired) && ($left === null || $date <= $left);

            if (! $employed || ! $employee->worksOn($day)) {
                $days[$date] = ['expected' => 0, 'off' => null];

                continue;
            }

            $off = match (true) {
                $window->isHoliday($date) => 'holiday',
                $window->isOnLeave($employee->id, $date) => 'leave',
                default => null,
            };

            $days[$date] = ['expected' => $off === null ? $employee->expected_minutes_per_day : 0, 'off' => $off];
        }

        return $days;
    }

    /**
     * Expected minutes for each day from the first to the last date.
     *
     * @return array<string, int> date (Y-m-d) => minutes, with 0 for a day off
     */
    public function expectedByDay(Employee $employee, CarbonInterface|string $from, CarbonInterface|string $to): array
    {
        return array_map(fn (array $day): int => $day['expected'], $this->days($employee, $from, $to));
    }
}
