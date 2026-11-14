<?php

namespace App\Http\Controllers\Payroll;

use App\Actions\Payroll\AddAdjustment;
use App\Data\PaymentAccountData;
use App\Enums\AdjustmentType;
use App\Enums\EmployeeStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Payroll\AdjustmentRequest;
use App\Models\Account;
use App\Models\Employee;
use App\Models\SalaryAdjustment;
use App\Models\User;
use App\Support\AlgerianDate;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Everything here sits behind the `view-salaries` gate (see routes/web.php). */
class AdjustmentController extends Controller
{
    public function index(Request $request): Response
    {
        $actor = $this->actor($request);
        $month = $this->month($request->string('month')->toString());

        $adjustments = SalaryAdjustment::query()
            ->with(['employee' => fn ($query) => $query->select('id', 'name', 'office_id')->withTrashed(), 'account:id,name'])
            ->whereDate('month', $month->toDateString())
            ->whereHas('employee', fn ($query) => $query->visibleTo($actor))
            ->orderBy('type')
            ->orderBy('id')
            ->get()
            ->map(fn (SalaryAdjustment $adjustment): array => [
                'id' => $adjustment->id,
                'employeeId' => $adjustment->employee_id,
                'employeeName' => $adjustment->employee->name ?? '',
                'type' => $adjustment->type->value,
                'typeLabel' => $adjustment->type->label(),
                'amountCents' => $adjustment->amount_cents,
                'amountFormatted' => Money::fromCents($adjustment->amount_cents)->format(),
                'reason' => $adjustment->reason,
                'paidOn' => $adjustment->paid_on?->toDateString(),
                'accountName' => $adjustment->account->name ?? null,
            ])
            ->values()
            ->all();

        return Inertia::render('payroll/Adjustments', [
            'month' => $month->format('Y-m'),
            'monthLabel' => $month->format('F Y'),
            'adjustments' => $adjustments,
            'employees' => Employee::query()
                ->visibleTo($actor)
                ->where('status', EmployeeStatus::Active)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (Employee $employee): array => ['id' => $employee->id, 'name' => $employee->name])
                ->values()
                ->all(),
            'accounts' => $this->accounts($actor),
            'types' => collect(AdjustmentType::cases())
                ->map(fn (AdjustmentType $type): array => ['value' => $type->value, 'label' => $type->label()])
                ->all(),
        ]);
    }

    public function store(AdjustmentRequest $request, AddAdjustment $add): RedirectResponse
    {
        $actor = $this->actor($request);
        $employee = Employee::query()->visibleTo($actor)->findOrFail($request->integer('employee_id'));
        $this->authorize('setSalary', $employee);

        $data = $request->validated();
        $data['month'] = $data['month'].'-01';

        $add->handle($actor, $employee, $data);

        return back(303)->with('success', __('Saved.'));
    }

    /**
     * @return array<int, PaymentAccountData>
     */
    private function accounts(User $actor): array
    {
        return Account::query()
            ->visibleTo($actor)
            ->where('active', true)
            ->with('office:id,name')
            ->orderBy('office_id')
            ->orderBy('name')
            ->get()
            ->map(fn (Account $account): PaymentAccountData => PaymentAccountData::fromAccount($account))
            ->values()
            ->all();
    }

    private function month(string $value): CarbonImmutable
    {
        return preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $value) === 1
            ? CarbonImmutable::parse($value.'-01')
            : AlgerianDate::today()->startOfMonth();
    }
}
