<?php

namespace App\Http\Controllers\Attendance;

use App\Attendance\MonthlySummary;
use App\Data\MonthlySummaryRowData;
use App\Enums\EmployeeStatus;
use App\Http\Controllers\Controller;
use App\Models\AttendanceSession;
use App\Models\Employee;
use App\Support\AlgerianDate;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * One row per employee for a month: worked, expected, to make up. Information
 * for the owner; it is also what the payslip and the attendance export show.
 */
class AttendanceSummaryController extends Controller
{
    public function __invoke(Request $request, MonthlySummary $summary): Response
    {
        $this->authorize('viewAny', AttendanceSession::class);

        $current = AlgerianDate::today()->startOfMonth();
        $month = $this->month($request->query('month'), $current);

        $employees = Employee::query()
            ->where(function ($query) use ($month): void {
                // Active people, plus anyone who left during or after this month.
                $query->where('status', EmployeeStatus::Active)
                    ->orWhereDate('left_on', '>=', $month->toDateString());
            })
            ->with('office:id,name')
            ->orderBy('name')
            ->get();

        $rows = $summary->forMany($employees, $month);
        $from = $month->toDateString();
        $to = $month->endOfMonth()->toDateString();

        return Inertia::render('attendance/Summary', [
            'month' => $month->format('Y-m'),
            'monthLabel' => $month->format('F Y'),
            'previousMonth' => $month->subMonth()->format('Y-m'),
            'nextMonth' => $month->lt($current) ? $month->addMonth()->format('Y-m') : null,
            'rows' => array_values($employees
                ->map(fn (Employee $employee): MonthlySummaryRowData => MonthlySummaryRowData::fromSummary(
                    $employee,
                    $rows[$employee->id],
                    '/attendance/sessions?'.http_build_query(['employee' => $employee->id, 'from' => $from, 'to' => $to]),
                ))
                ->all()),
        ]);
    }

    /** A month as Y-m, never later than the current one. */
    private function month(mixed $value, CarbonImmutable $current): CarbonImmutable
    {
        if (! is_string($value) || preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $value) !== 1) {
            return $current;
        }

        $month = CarbonImmutable::parse($value.'-01', AlgerianDate::TZ)->startOfMonth();

        return $month->gt($current) ? $current : $month;
    }
}
