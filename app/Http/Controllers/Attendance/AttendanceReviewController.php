<?php

namespace App\Http\Controllers\Attendance;

use App\Data\AttendanceEmployeeOptionData;
use App\Data\AttendanceSessionData;
use App\Http\Controllers\Controller;
use App\Models\AttendanceSession;
use App\Models\Employee;
use App\Queries\AttendanceReviewQuery;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AttendanceReviewController extends Controller
{
    public function __invoke(Request $request, AttendanceReviewQuery $review): Response
    {
        $this->authorize('viewAny', AttendanceSession::class);

        $perPage = $request->integer('per_page', 25);
        $sessions = $review->rows(perPage: in_array($perPage, [10, 25, 50], true) ? $perPage : 25);

        return Inertia::render('attendance/Review', [
            'sessions' => collect($sessions->items())
                ->map(fn (AttendanceSession $session): AttendanceSessionData => AttendanceSessionData::fromSession($session))
                ->values()
                ->all(),
            'total' => $sessions->total(),
            'page' => $sessions->currentPage(),
            'perPage' => $sessions->perPage(),
            'employees' => Employee::query()
                ->with('office:id,name')
                ->orderBy('name')
                ->get()
                ->map(fn (Employee $employee): AttendanceEmployeeOptionData => AttendanceEmployeeOptionData::fromEmployee($employee))
                ->values()
                ->all(),
        ]);
    }
}
