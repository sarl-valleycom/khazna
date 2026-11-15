<?php

namespace App\Attendance;

use App\Enums\AttendanceReviewStatus;
use App\Enums\AttendanceStatus;
use App\Models\AttendanceSession;
use App\Models\AttendanceSetting;
use App\Models\Employee;
use App\Support\AlgerianDate;
use Carbon\CarbonImmutable;

/**
 * A month of attendance for each employee: worked, expected, what is left to
 * make up and what is extra. It is information. It never changes a salary.
 *
 * Every number comes from the same day-by-day logic as HoursBalance, so the
 * employee page and the documents agree. For the current month the target runs
 * through today only, so a month in progress is not shown as a debt.
 */
class MonthlySummary
{
    public function __construct(private readonly WorkCalendar $calendar) {}

    /**
     * @param  iterable<Employee>  $employees
     * @return array<int, array<string, int>> employee id => the summary
     */
    public function forMany(iterable $employees, CarbonImmutable $month, ?CarbonImmutable $at = null): array
    {
        $employees = collect($employees)->values();

        if ($employees->isEmpty()) {
            return [];
        }

        $now = ($at ?? AlgerianDate::now())->utc();
        $today = AlgerianDate::toAlgiers($now)->startOfDay();
        $first = AlgerianDate::toAlgiers($month)->startOfMonth()->startOfDay();
        $last = $first->endOfMonth()->startOfDay();
        $through = $last->lt($today) ? $last : $today;

        if ($first->gt($today)) {
            return $employees->mapWithKeys(fn (Employee $employee): array => [$employee->id => $this->empty()])->all();
        }

        $ids = $employees->map(fn (Employee $employee): int => $employee->id)->all();
        $allowance = AttendanceSetting::current()->paid_break_minutes;

        $sessions = AttendanceSession::query()
            ->whereIn('employee_id', $ids)
            ->whereBetween('started_at', [$first->utc(), $last->endOfDay()->utc()])
            ->with('breaks')
            ->get()
            ->groupBy('employee_id');

        $window = $this->calendar->window($first, $through, $ids);
        $summary = [];

        foreach ($employees as $employee) {
            $mine = $sessions->get($employee->id, collect());
            $days = $this->calendar->days($employee, $first, $through, $window);
            $workedByDay = WorkedTime::minutesByDay($mine, $now, $allowance);

            $expected = array_sum(array_column($days, 'expected'));
            $worked = array_sum($workedByDay);

            $summary[$employee->id] = [
                'expected' => $expected,
                'worked' => $worked,
                'to_make_up' => max(0, $expected - $worked),
                'extra' => max(0, $worked - $expected),
                'days_worked' => count(array_filter($workedByDay, fn (int $minutes): bool => $minutes > 0)),
                'days_off' => count(array_filter($days, fn (array $day): bool => $day['off'] !== null)),
                'not_checked' => $mine->filter(fn (AttendanceSession $session): bool => $session->status === AttendanceStatus::AutoClosed
                    && $session->review_status === AttendanceReviewStatus::NeedsReview)->count(),
            ];
        }

        return $summary;
    }

    /**
     * @return array<string, int>
     */
    public function for(Employee $employee, CarbonImmutable $month, ?CarbonImmutable $at = null): array
    {
        return $this->forMany([$employee], $month, $at)[$employee->id];
    }

    /**
     * @return array<string, int>
     */
    private function empty(): array
    {
        return ['expected' => 0, 'worked' => 0, 'to_make_up' => 0, 'extra' => 0, 'days_worked' => 0, 'days_off' => 0, 'not_checked' => 0];
    }
}
