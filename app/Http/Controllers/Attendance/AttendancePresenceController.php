<?php

namespace App\Http\Controllers\Attendance;

use App\Attendance\WorkedTime;
use App\Data\AttendancePresenceData;
use App\Enums\EmployeeStatus;
use App\Http\Controllers\Controller;
use App\Models\AttendanceSession;
use App\Models\AttendanceSetting;
use App\Models\Employee;
use App\Support\AlgerianDate;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AttendancePresenceController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $this->authorize('viewAny', AttendanceSession::class);

        $settings = AttendanceSetting::current();
        $openSessions = AttendanceSession::query()
            ->open()
            ->with('breaks')
            ->get()
            ->keyBy('employee_id');

        $rows = Employee::query()
            ->where('status', EmployeeStatus::Active)
            ->with('office:id,name')
            ->orderBy('name')
            ->get()
            ->map(function (Employee $employee) use ($openSessions, $settings): AttendancePresenceData {
                $session = $openSessions->get($employee->id);
                $break = $session?->breaks->first(fn ($break): bool => $break->ended_at === null);

                $state = match (true) {
                    $session === null => 'offline',
                    $break !== null => 'away',
                    default => 'working',
                };

                $since = null;

                if ($break !== null) {
                    $since = $break->started_at;
                } elseif ($session !== null) {
                    $since = $session->started_at;
                }
                $sinceLocal = $since?->setTimezone(AlgerianDate::TZ);
                $sinceLabel = $sinceLocal === null
                    ? ''
                    : ($sinceLocal->toDateString() === AlgerianDate::todayString()
                        ? 'since '.$sinceLocal->format('H:i')
                        : 'since '.$sinceLocal->format('d M, H:i'));

                $todayMinutes = WorkedTime::todayMinutes($employee, null, $settings->paid_break_minutes);

                return new AttendancePresenceData(
                    employeeId: $employee->id,
                    name: $employee->name,
                    officeName: $employee->office->name ?? '',
                    state: $state,
                    stateLabel: match ($state) {
                        'working' => 'Working',
                        'away' => 'Away',
                        default => 'Offline',
                    },
                    since: $since?->toIso8601String(),
                    sinceLabel: $sinceLabel,
                    todayMinutes: $todayMinutes,
                    todayFormatted: WorkedTime::format($todayMinutes),
                );
            })
            ->sortBy(fn (AttendancePresenceData $row): string => sprintf(
                '%d-%s',
                ['working' => 0, 'away' => 1, 'offline' => 2][$row->state] ?? 3,
                $row->name,
            ))
            ->values();

        return Inertia::render('attendance/Presence', [
            'presence' => $rows->all(),
        ]);
    }
}
