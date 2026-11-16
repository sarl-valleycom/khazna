<?php

namespace App\Http\Controllers\Employees;

use App\Attendance\AttendanceCode;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use Illuminate\Http\RedirectResponse;

/**
 * Only the owner gives an employee a personal attendance code (whoever holds
 * it can act as that employee on the attendance page) and can read it again
 * on the employee page.
 */
class AttendanceCodeController extends Controller
{
    public function store(Employee $employee, AttendanceCode $codes): RedirectResponse
    {
        $this->authorize('manage-attendance-codes');

        abort_unless($employee->isActive(), 422);

        $codes->generate($employee);

        return back(303)->with('success', __('New attendance code made for :name.', ['name' => $employee->name]));
    }

    public function destroy(Employee $employee, AttendanceCode $codes): RedirectResponse
    {
        $this->authorize('manage-attendance-codes');

        $codes->revoke($employee);

        return back(303)->with('success', __('Attendance code removed.'));
    }
}
