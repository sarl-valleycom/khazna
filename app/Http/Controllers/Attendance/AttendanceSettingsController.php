<?php

namespace App\Http\Controllers\Attendance;

use App\Actions\Attendance\UpdateAttendanceSettings;
use App\Data\AttendanceSettingsData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Attendance\AttendanceSettingsRequest;
use App\Models\AttendanceSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AttendanceSettingsController extends Controller
{
    public function edit(Request $request): Response
    {
        $this->authorize('viewAny', AttendanceSetting::class);

        return Inertia::render('attendance/Settings', [
            'settings' => AttendanceSettingsData::fromCurrent(),
        ]);
    }

    public function update(AttendanceSettingsRequest $request, UpdateAttendanceSettings $update): RedirectResponse
    {
        $this->authorize('update', AttendanceSetting::class);

        $update->handle($request->validated());

        return to_route('attendance.settings.edit', [], 303)->with('success', __('Attendance settings saved.'));
    }
}
