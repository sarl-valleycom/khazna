<?php

namespace App\Http\Controllers\Employees;

use App\Attendance\AttendanceCode;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use Illuminate\Http\RedirectResponse;

/**
 * The owner gives an employee a personal attendance code. The code is shown
 * once, right after it is made, and cannot be read back: a lost code is
 * replaced by making a new one.
 */
class AttendanceCodeController extends Controller
{
    public function store(Employee $employee, AttendanceCode $codes): RedirectResponse
    {
        $this->authorize('update', $employee);

        abort_unless($employee->isActive(), 422);

        return back(303)
            ->with('attendance_code', $codes->generate($employee))
            ->with('success', __('New attendance code made. Give it to :name now; it is not shown again.', ['name' => $employee->name]));
    }

    public function destroy(Employee $employee, AttendanceCode $codes): RedirectResponse
    {
        $this->authorize('update', $employee);

        $codes->revoke($employee);

        return back(303)->with('success', __('Attendance code removed.'));
    }
}
