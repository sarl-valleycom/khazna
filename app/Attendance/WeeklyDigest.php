<?php

namespace App\Attendance;

use App\Enums\EmployeeStatus;
use App\Models\AttendanceSession;
use App\Models\AttendanceSetting;
use App\Models\Employee;
use App\Models\Office;
use App\Support\AlgerianDate;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Sunday morning summary of the previous week (Sunday to Saturday, so weekend
 * work is counted): worked minutes against expected minutes, late sessions and
 * overtime. Expected minutes follow each employee's own work days.
 */
class WeeklyDigest
{
    public function __construct(private readonly WorkCalendar $calendar) {}

    /**
     * @return array<string, mixed>
     */
    public function embed(CarbonImmutable $now): array
    {
        $start = AlgerianDate::toAlgiers($now)
            ->startOfWeek(CarbonInterface::SUNDAY)
            ->subWeek()
            ->startOfDay();
        $end = $start->addDays(6)->endOfDay();
        $allowance = AttendanceSetting::current()->paid_break_minutes;

        $sessions = AttendanceSession::query()
            ->whereBetween('started_at', [$start->utc(), $end->utc()])
            ->with(['employee:id,name,office_id,discord_user_id,schedule_start,schedule_end,late_grace_minutes,expected_minutes_per_day', 'employee.office:id,name', 'breaks'])
            ->orderBy('started_at')
            ->get()
            ->groupBy('employee_id');

        $employees = Employee::query()
            ->where('status', EmployeeStatus::Active)
            ->with('office:id,name')
            ->orderBy('name')
            ->get();

        $fields = [];

        foreach (Office::query()->orderBy('name')->get() as $office) {
            $lines = [];

            foreach ($employees->where('office_id', $office->id) as $employee) {
                $employeeSessions = $sessions->get($employee->id, collect());
                $worked = $this->workedMinutes($employeeSessions, $now, $allowance);
                $expected = $this->calendar->expectedMinutes($employee, $start, $end);
                $late = $this->lateCount($employee, $employeeSessions);
                $overtime = max(0, $worked - $expected);

                $lines[] = $this->mention($employee)
                    .' — '.WorkedTime::format($worked).' / '.WorkedTime::format($expected)
                    .' · late '.$late
                    .' · overtime '.WorkedTime::format($overtime);
            }

            $fields[] = [
                'name' => $office->name,
                'value' => $lines === [] ? 'No active employees.' : mb_substr(implode("\n", $lines), 0, 1024),
                'inline' => false,
            ];
        }

        if ($fields === []) {
            $fields[] = [
                'name' => 'No offices',
                'value' => 'No offices are configured.',
                'inline' => false,
            ];
        }

        return [
            'title' => 'Weekly attendance — '.$start->format('d M').' to '.$end->format('d M Y'),
            'color' => 0x16A34A,
            'fields' => array_slice($fields, 0, 25),
            'footer' => ['text' => 'Khazna Attendance'],
            'timestamp' => $now->utc()->toIso8601String(),
        ];
    }

    /**
     * @param  Collection<int, AttendanceSession>  $sessions
     */
    private function workedMinutes(Collection $sessions, CarbonImmutable $now, int $allowance): int
    {
        return WorkedTime::minutesForSessions($sessions, $now->utc(), $allowance);
    }

    /**
     * @param  Collection<int, AttendanceSession>  $sessions
     */
    private function lateCount(Employee $employee, Collection $sessions): int
    {
        if ($employee->schedule_start === null || $employee->schedule_start === '') {
            return 0;
        }

        return $sessions->filter(function (AttendanceSession $session) use ($employee): bool {
            $day = $session->started_at->setTimezone(AlgerianDate::TZ);

            if (! $employee->worksOn($day) || ! $this->calendar->isWorkingDay($employee, $day)) {
                return false;
            }

            $expected = CarbonImmutable::parse($day->toDateString().' '.$employee->schedule_start, AlgerianDate::TZ)
                ->addMinutes($employee->late_grace_minutes);

            return $session->started_at->gt($expected->utc());
        })->count();
    }

    private function mention(Employee $employee): string
    {
        return $employee->isLinked() ? '<@'.$employee->discord_user_id.'>' : $employee->name;
    }
}
