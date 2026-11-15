<?php

namespace App\Http\Controllers\Attendance;

use App\Actions\Attendance\AddHoliday;
use App\Actions\Attendance\RemoveHoliday;
use App\Data\HolidayData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Attendance\AttendanceReasonRequest;
use App\Http\Requests\Attendance\HolidayRequest;
use App\Models\Holiday;
use App\Support\AlgerianDate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HolidayController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Holiday::class);

        $year = $request->integer('year', (int) AlgerianDate::today()->format('Y'));
        $year = min(max($year, 2000), 2100);

        $holidays = Holiday::query()
            ->whereYear('date', $year)
            ->orderBy('date')
            ->get()
            ->map(fn (Holiday $holiday): HolidayData => HolidayData::fromHoliday($holiday))
            ->values()
            ->all();

        return Inertia::render('attendance/Holidays', [
            'year' => $year,
            'holidays' => $holidays,
        ]);
    }

    public function store(HolidayRequest $request, AddHoliday $add): RedirectResponse
    {
        $this->authorize('create', Holiday::class);

        $validated = $request->validated();
        $add->handle($validated['date'], trim($validated['label']));

        return back(303)->with('success', __('Holiday saved.'));
    }

    public function destroy(AttendanceReasonRequest $request, Holiday $holiday, RemoveHoliday $remove): RedirectResponse
    {
        $this->authorize('delete', $holiday);

        $remove->handle($holiday, (string) $request->validated('reason'));

        return back(303)->with('success', __('Holiday removed.'));
    }
}
