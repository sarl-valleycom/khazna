<?php

namespace App\Http\Controllers\Employees;

use App\Actions\Payroll\SetSalary;
use App\Http\Controllers\Controller;
use App\Http\Requests\Employees\SalaryRequest;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

class SalaryController extends Controller
{
    public function store(SalaryRequest $request, Employee $employee, SetSalary $setSalary): RedirectResponse
    {
        $this->authorize('setSalary', $employee);

        $actor = $request->user();

        $setSalary->handle(
            $employee,
            (int) $request->validated('amount_cents'),
            (string) $request->validated('effective_from'),
            $actor instanceof User ? $actor : null,
        );

        return back(303)->with('success', __('Salary saved.'));
    }
}
