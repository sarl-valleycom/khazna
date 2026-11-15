<?php

namespace App\Attendance;

use App\Models\LeaveRequest;
use Illuminate\Support\Collection;

/**
 * The holidays and the approved leave for a range of dates, loaded once so a
 * whole team can be worked out without a query per person.
 */
final class CalendarWindow
{
    /**
     * @param  array<string, bool>  $holidays  date (Y-m-d) => true
     * @param  array<int, Collection<int, LeaveRequest>>  $leaves  employee id => their leave
     */
    public function __construct(
        public readonly array $holidays,
        public readonly array $leaves,
    ) {}

    public function isHoliday(string $date): bool
    {
        return isset($this->holidays[$date]);
    }

    public function isOnLeave(int $employeeId, string $date): bool
    {
        return isset($this->leaves[$employeeId])
            && $this->leaves[$employeeId]->contains(fn (LeaveRequest $leave): bool => $leave->coversOn($date));
    }
}
