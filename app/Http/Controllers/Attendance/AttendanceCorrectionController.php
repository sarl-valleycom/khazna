<?php

namespace App\Http\Controllers\Attendance;

use App\Actions\Attendance\DecideCorrection;
use App\Data\CorrectionRequestData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Attendance\AttendanceReasonRequest;
use App\Models\CorrectionRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AttendanceCorrectionController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', CorrectionRequest::class);

        $rows = CorrectionRequest::query()
            ->pending()
            ->with(['employee:id,name,office_id', 'employee.office:id,name', 'session'])
            ->orderBy('created_at')
            ->get()
            ->map(fn (CorrectionRequest $request): CorrectionRequestData => CorrectionRequestData::fromRequest($request))
            ->values()
            ->all();

        return Inertia::render('attendance/Corrections', [
            'requests' => $rows,
        ]);
    }

    public function approve(
        AttendanceReasonRequest $request,
        CorrectionRequest $correctionRequest,
        DecideCorrection $decide,
    ): RedirectResponse {
        $this->authorize('decide', $correctionRequest);

        $decide->handle($correctionRequest, true, (string) $request->validated('reason'), $this->actor($request));

        return back(303)->with('success', __('Correction approved.'));
    }

    public function reject(
        AttendanceReasonRequest $request,
        CorrectionRequest $correctionRequest,
        DecideCorrection $decide,
    ): RedirectResponse {
        $this->authorize('decide', $correctionRequest);

        $decide->handle($correctionRequest, false, (string) $request->validated('reason'), $this->actor($request));

        return back(303)->with('success', __('Correction rejected.'));
    }
}
