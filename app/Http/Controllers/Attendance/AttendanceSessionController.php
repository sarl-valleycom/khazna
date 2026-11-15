<?php

namespace App\Http\Controllers\Attendance;

use App\Actions\Attendance\ApproveSession;
use App\Actions\Attendance\CreateSession;
use App\Actions\Attendance\DeleteSession;
use App\Actions\Attendance\UpdateSession;
use App\Data\AttendanceEmployeeOptionData;
use App\Data\AttendanceFiltersData;
use App\Data\AttendanceSessionData;
use App\Data\OfficeOptionData;
use App\Enums\AttendanceStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Attendance\AttendanceReasonRequest;
use App\Http\Requests\Attendance\AttendanceSessionRequest;
use App\Models\AttendanceSession;
use App\Models\Employee;
use App\Models\Office;
use App\Queries\SessionIndexQuery;
use App\Support\AlgerianDate;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AttendanceSessionController extends Controller
{
    public function __construct(private readonly SessionIndexQuery $sessions) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', AttendanceSession::class);

        $filters = AttendanceFiltersData::fromRequest($request);
        $sessions = $this->sessions->rows($filters);

        return Inertia::render('attendance/Sessions', [
            'sessions' => collect($sessions->items())
                ->map(fn (AttendanceSession $session): AttendanceSessionData => AttendanceSessionData::fromSession($session))
                ->values()
                ->all(),
            'total' => $sessions->total(),
            'page' => $sessions->currentPage(),
            'perPage' => $sessions->perPage(),
            'sort' => $filters->sort,
            'direction' => $filters->direction,
            'filters' => $filters,
            'employees' => $this->employees(),
            'offices' => $this->offices(),
            'statuses' => collect(AttendanceStatus::cases())
                ->map(fn (AttendanceStatus $status): array => ['value' => $status->value, 'label' => $status->label()])
                ->all(),
        ]);
    }

    public function store(AttendanceSessionRequest $request, CreateSession $create): RedirectResponse
    {
        $this->authorize('create', AttendanceSession::class);

        $validated = $request->validated();
        $employee = Employee::query()
            ->where('id', (int) $validated['employee_id'])
            ->firstOrFail();

        try {
            $create->handle(
                employee: $employee,
                startedAt: $this->dateTime((string) $validated['started_at']),
                endedAt: isset($validated['ended_at']) ? $this->dateTime((string) $validated['ended_at']) : null,
                report: $validated['report'] ?? null,
                blockers: $validated['blockers'] ?? null,
                reason: (string) $validated['reason'],
                actor: $this->actor($request),
            );
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'ended_at' => __('This employee already has an open session.'),
            ]);
        }

        return back(303)->with('success', __('Session added.'));
    }

    public function update(
        AttendanceSessionRequest $request,
        AttendanceSession $attendanceSession,
        UpdateSession $update,
    ): RedirectResponse {
        $this->authorize('update', $attendanceSession);

        $validated = $request->validated();

        if ((int) $validated['employee_id'] !== $attendanceSession->employee_id) {
            throw ValidationException::withMessages([
                'employee_id' => __('A session cannot be moved to another employee.'),
            ]);
        }

        try {
            $update->handle(
                session: $attendanceSession,
                startedAt: $this->dateTime((string) $validated['started_at']),
                endedAt: isset($validated['ended_at']) ? $this->dateTime((string) $validated['ended_at']) : null,
                report: $validated['report'] ?? null,
                blockers: $validated['blockers'] ?? null,
                reason: (string) $validated['reason'],
                actor: $this->actor($request),
            );
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'ended_at' => __('This employee already has an open session.'),
            ]);
        }

        return back(303)->with('success', __('Session updated.'));
    }

    public function destroy(
        AttendanceReasonRequest $request,
        AttendanceSession $attendanceSession,
        DeleteSession $delete,
    ): RedirectResponse {
        $this->authorize('delete', $attendanceSession);

        $delete->handle($attendanceSession, (string) $request->validated('reason'));

        return back(303)->with('success', __('Session deleted.'));
    }

    public function approve(
        AttendanceReasonRequest $request,
        AttendanceSession $attendanceSession,
        ApproveSession $approve,
    ): RedirectResponse {
        $this->authorize('approve', $attendanceSession);

        $approve->handle($attendanceSession, (string) $request->validated('reason'));

        return back(303)->with('success', __('Session approved.'));
    }

    private function dateTime(string $value): CarbonImmutable
    {
        return CarbonImmutable::parse($value, AlgerianDate::TZ)->utc();
    }

    /**
     * @return list<AttendanceEmployeeOptionData>
     */
    private function employees(): array
    {
        $employees = Employee::query()
            ->with('office:id,name')
            ->orderBy('name')
            ->get()
            ->map(fn (Employee $employee): AttendanceEmployeeOptionData => AttendanceEmployeeOptionData::fromEmployee($employee))
            ->all();

        return array_values($employees);
    }

    /**
     * @return list<OfficeOptionData>
     */
    private function offices(): array
    {
        $offices = Office::query()
            ->orderBy('name')
            ->get()
            ->map(fn (Office $office): OfficeOptionData => new OfficeOptionData($office->id, $office->name, $office->city))
            ->all();

        return array_values($offices);
    }
}
