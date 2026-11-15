<?php

namespace App\Attendance;

use App\Discord\EmbedSplitter;
use App\Enums\AttendanceReviewStatus;
use App\Enums\AttendanceStatus;
use App\Enums\EmployeeStatus;
use App\Models\AttendanceSession;
use App\Models\Employee;
use App\Models\Office;
use App\Support\AlgerianDate;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Builds the owner's end-of-day digest: who is in, late, absent, away or
 * forgotten, plus the reports, grouped by office. The command only posts the
 * finished embed.
 */
class DailyDigest
{
    public function __construct(private readonly WorkCalendar $calendar) {}

    /**
     * The digest as one or more embeds that each fit Discord's limits. Send
     * every embed as its own message.
     *
     * @return list<array<string, mixed>>
     */
    public function embeds(CarbonImmutable $now): array
    {
        $today = $now->toDateString();
        [$from, $to] = AlgerianDate::dayRangeUtc($today);

        $sessions = AttendanceSession::query()
            ->whereBetween('started_at', [$from, $to])
            ->with([
                'employee:id,name,office_id,discord_user_id,schedule_start,schedule_end,late_grace_minutes',
                'employee.office:id,name',
                'breaks',
            ])
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
            $officeEmployees = $employees->where('office_id', $office->id);

            $in = [];
            $away = [];
            $late = [];
            $absent = [];
            $forgotten = [];
            $reports = [];

            foreach ($officeEmployees as $employee) {
                $employeeSessions = $sessions->get($employee->id, collect());

                $this->addOpenState($employee, $employeeSessions, $in, $away);

                foreach ($employeeSessions as $session) {
                    $this->addIfLate($employee, $session, $late);

                    if ($session->status === AttendanceStatus::AutoClosed && $session->review_status === AttendanceReviewStatus::NeedsReview) {
                        $forgotten[] = $this->mention($employee).' — closed automatically at '.$this->time($session->ended_at);
                    }

                    if ($session->report !== null && trim($session->report) !== '') {
                        $line = $this->mention($employee).' — '.trim($session->report);

                        if ($session->blockers !== null && trim($session->blockers) !== '') {
                            $line .= ' (Blockers: '.trim($session->blockers).')';
                        }

                        $reports[] = $line;
                    }
                }

                if ($this->isAbsent($employee, $employeeSessions, $now)) {
                    $absent[] = $this->mention($employee);
                }
            }

            $fields[] = [
                'name' => $office->name,
                'value' => $this->fieldValue([
                    'In' => $in,
                    'Late' => $late,
                    'Absent' => $absent,
                    'Away' => $away,
                    'Forgotten' => $forgotten,
                    'Reports' => $reports,
                ]),
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

        return EmbedSplitter::split([
            'title' => 'Attendance — '.$now->format('D, d M Y'),
            'color' => 0x2563EB,
            'fields' => $fields,
            'footer' => ['text' => 'Khazna Attendance'],
            'timestamp' => $now->utc()->toIso8601String(),
        ]);
    }

    /**
     * @param  Collection<int, AttendanceSession>  $sessions
     * @param  list<string>  $in
     * @param  list<string>  $away
     */
    private function addOpenState(Employee $employee, Collection $sessions, array &$in, array &$away): void
    {
        $session = $sessions->first(fn (AttendanceSession $session): bool => $session->ended_at === null);

        if ($session === null) {
            return;
        }

        $break = $session->breaks->first(fn ($break): bool => $break->ended_at === null);
        $line = $this->mention($employee).' (since '.$this->time($session->started_at).')';

        if ($break === null) {
            $in[] = $line;

            return;
        }

        $away[] = $line;
    }

    /**
     * @param  list<string>  $late
     */
    private function addIfLate(Employee $employee, AttendanceSession $session, array &$late): void
    {
        if ($employee->schedule_start === null || $employee->schedule_start === '') {
            return;
        }

        $day = $session->started_at->setTimezone(AlgerianDate::TZ);

        if (! $employee->worksOn($day) || ! $this->calendar->isWorkingDay($employee, $day)) {
            return;
        }

        $expected = CarbonImmutable::parse($day->toDateString().' '.$employee->schedule_start, AlgerianDate::TZ)
            ->addMinutes($employee->late_grace_minutes);

        if ($session->started_at->gt($expected->utc())) {
            $late[] = $this->mention($employee).' — '.$this->time($session->started_at).' (expected '.$expected->format('H:i').')';
        }
    }

    /**
     * @param  Collection<int, AttendanceSession>  $sessions
     */
    private function isAbsent(Employee $employee, Collection $sessions, CarbonImmutable $now): bool
    {
        if ($employee->schedule_start === null || $employee->schedule_start === '' || $sessions->isNotEmpty()) {
            return false;
        }

        $start = CarbonImmutable::parse($now->toDateString().' '.$employee->schedule_start, AlgerianDate::TZ);

        if ($now->lt($start) || ! $this->calendar->isWorkingDay($employee, $now)) {
            return false;
        }

        return true;
    }

    private function mention(Employee $employee): string
    {
        return $employee->isLinked() ? '<@'.$employee->discord_user_id.'>' : $employee->name;
    }

    private function time(?CarbonImmutable $at): string
    {
        return $at === null ? '—' : $at->setTimezone(AlgerianDate::TZ)->format('H:i');
    }

    /**
     * @param  array<string, list<string>>  $categories
     */
    private function fieldValue(array $categories): string
    {
        $lines = [];

        foreach ($categories as $label => $items) {
            if ($items === []) {
                continue;
            }

            $lines[] = '**'.$label.'**';

            foreach ($items as $item) {
                $lines[] = mb_substr($item, 0, 300);
            }
        }

        if ($lines === []) {
            return 'No attendance recorded yet.';
        }

        return implode("\n", $lines);
    }
}
