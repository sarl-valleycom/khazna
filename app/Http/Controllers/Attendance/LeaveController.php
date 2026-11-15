<?php

namespace App\Http\Controllers\Attendance;

use App\Actions\Attendance\AddLeave;
use App\Actions\Attendance\CancelLeave;
use App\Data\AttendanceEmployeeOptionData;
use App\Data\LeaveEntryData;
use App\Enums\EmployeeStatus;
use App\Enums\LeaveStatus;
use App\Enums\LeaveType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Attendance\AttendanceReasonRequest;
use App\Http\Requests\Attendance\LeaveEntryRequest;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Support\AlgerianDate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LeaveController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', LeaveRequest::class);

        $year = $request->integer('year', (int) AlgerianDate::today()->format('Y'));
        $year = min(max($year, 2000), 2100);

        $leaves = LeaveRequest::query()
            ->where('status', LeaveStatus::Approved)
            ->whereDate('from', '<=', "{$year}-12-31")
            ->whereDate('to', '>=', "{$year}-01-01")
            ->with(['employee.office'])
            ->orderByDesc('from')
            ->get()
            ->map(fn (LeaveRequest $leave): LeaveEntryData => LeaveEntryData::fromLeave($leave))
            ->values()
            ->all();

        return Inertia::render('attendance/Leave', [
            'year' => $year,
            'leaves' => $leaves,
            'employees' => Employee::query()
                ->where('status', EmployeeStatus::Active)
                ->with('office:id,name')
                ->orderBy('name')
                ->get()
                ->map(fn (Employee $employee): AttendanceEmployeeOptionData => AttendanceEmployeeOptionData::fromEmployee($employee))
                ->values()
                ->all(),
            'types' => collect(LeaveType::cases())
                ->map(fn (LeaveType $type): array => ['value' => $type->value, 'label' => $type->label()])
                ->all(),
        ]);
    }

    public function store(LeaveEntryRequest $request, AddLeave $add): RedirectResponse
    {
        $this->authorize('create', LeaveRequest::class);

        $validated = $request->validated();

        $add->handle(
            Employee::query()->findOrFail((int) $validated['employee_id']),
            LeaveType::from($validated['type']),
            $validated['from'],
            $validated['to'],
            $validated['reason'] ?? null,
            $this->actor($request),
        );

        return back(303)->with('success', __('Leave saved.'));
    }

    public function destroy(AttendanceReasonRequest $request, LeaveRequest $leave, CancelLeave $cancel): RedirectResponse
    {
        $this->authorize('delete', $leave);

        $cancel->handle($leave, (string) $request->validated('reason'));

        return back(303)->with('success', __('Leave cancelled.'));
    }
}
