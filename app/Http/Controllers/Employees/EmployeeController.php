<?php

namespace App\Http\Controllers\Employees;

use App\Actions\Payroll\SetSalary;
use App\Data\EmployeeData;
use App\Data\EmployeeWithSalaryData;
use App\Data\OfficeOptionData;
use App\Enums\EmployeeStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Employees\EmployeeRequest;
use App\Models\Employee;
use App\Models\EmployeeSalary;
use App\Models\Office;
use App\Models\User;
use App\Support\AlgerianDate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class EmployeeController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Employee::class);

        $actor = $this->actor($request);
        $withSalary = $actor->can('view-salaries');

        $employees = Employee::query()
            ->visibleTo($actor)
            ->with('office:id,name')
            // The current salary comes from one subquery, and only for people who may see it.
            ->when($withSalary, fn (Builder $query) => $query->addSelect([
                'employees.*',
                'current_salary_cents' => EmployeeSalary::query()
                    ->select('amount_cents')
                    ->whereColumn('employee_id', 'employees.id')
                    ->whereDate('effective_from', '<=', AlgerianDate::todayString())
                    ->orderByDesc('effective_from')
                    ->limit(1),
            ]))
            ->orderByRaw("status = 'active' desc")
            ->orderBy('name')
            ->get();

        return Inertia::render('employees/Index', [
            'employees' => $employees
                ->map(fn (Employee $employee) => $withSalary
                    ? EmployeeWithSalaryData::fromEmployee($employee, false, $employee->getAttribute('current_salary_cents') === null ? null : (int) $employee->getAttribute('current_salary_cents'))
                    : EmployeeData::fromEmployee($employee))
                ->values()
                ->all(),
            'canSeeSalaries' => $withSalary,
            'offices' => $this->offices($actor),
            'statuses' => collect(EmployeeStatus::cases())
                ->map(fn (EmployeeStatus $status): array => ['value' => $status->value, 'label' => $status->label()])
                ->all(),
        ]);
    }

    public function show(Request $request, Employee $employee): Response
    {
        $this->authorize('view', $employee);

        $actor = $this->actor($request);
        $employee->load('office:id,name');

        return Inertia::render('employees/Show', [
            'employee' => $actor->can('view-salaries')
                ? EmployeeWithSalaryData::fromEmployee($employee, withHistory: true)
                : ['employee' => EmployeeData::fromEmployee($employee)],
            'canSeeSalaries' => $actor->can('view-salaries'),
            'offices' => $this->offices($actor),
            'statuses' => collect(EmployeeStatus::cases())
                ->map(fn (EmployeeStatus $status): array => ['value' => $status->value, 'label' => $status->label()])
                ->all(),
        ]);
    }

    public function store(EmployeeRequest $request, SetSalary $setSalary): RedirectResponse
    {
        $this->authorize('create', Employee::class);

        $actor = $this->actor($request);
        $validated = $request->validated();
        $this->assertOfficeAllowed($actor, (int) $validated['office_id']);

        DB::transaction(function () use ($validated, $setSalary, $actor): void {
            $employee = Employee::query()->create(collect($validated)->except(['salary_cents', 'salary_from'])->all());

            if (isset($validated['salary_cents'])) {
                $setSalary->handle($employee, (int) $validated['salary_cents'], (string) $validated['salary_from'], $actor);
            }
        });

        return back(303)->with('success', __('Employee created.'));
    }

    public function update(EmployeeRequest $request, Employee $employee): RedirectResponse
    {
        $this->authorize('update', $employee);

        $actor = $this->actor($request);
        $validated = $request->validated();
        $this->assertOfficeAllowed($actor, (int) $validated['office_id']);

        // A salary is never changed by editing the person: raises go through setSalary().
        $employee->update(collect($validated)->except(['salary_cents', 'salary_from'])->all());

        return back(303)->with('success', __('Employee updated.'));
    }

    private function assertOfficeAllowed(User $actor, int $officeId): void
    {
        abort_if($actor->office_id !== null && $actor->office_id !== $officeId, 403);
    }

    /**
     * @return array<int, OfficeOptionData>
     */
    private function offices(User $actor): array
    {
        return Office::query()
            ->visibleTo($actor)
            ->where('active', true)
            ->orderBy('name')
            ->get()
            ->map(fn (Office $office): OfficeOptionData => new OfficeOptionData($office->id, $office->name, $office->city))
            ->values()
            ->all();
    }

    private function actor(Request $request): User
    {
        $actor = $request->user();

        if (! $actor instanceof User) {
            abort(403);
        }

        return $actor;
    }
}
