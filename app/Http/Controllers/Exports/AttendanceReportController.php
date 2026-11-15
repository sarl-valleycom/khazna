<?php

namespace App\Http\Controllers\Exports;

use App\Attendance\MonthlySummary;
use App\Attendance\WorkedTime;
use App\Data\AttendanceFiltersData;
use App\Exports\AttendanceEmployeeExport;
use App\Exports\AttendanceSummaryExport;
use App\Http\Controllers\Controller;
use App\Models\AttendanceSession;
use App\Models\Employee;
use App\Pdf\PdfRenderer;
use App\Queries\SessionIndexQuery;
use App\Support\AlgerianDate;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * The monthly attendance, as files: the team in one Excel sheet, and one
 * employee's month as Excel or PDF. Owner only, like the monthly summary screen,
 * and built from the same MonthlySummary, so the files agree with the screen.
 */
class AttendanceReportController extends Controller
{
    public function team(Request $request, MonthlySummary $summary): BinaryFileResponse
    {
        $this->authorize('viewAny', AttendanceSession::class);

        $month = $this->month($request->query('month'));
        $employees = $summary->roster($month);

        return Excel::download(
            new AttendanceSummaryExport($employees, $summary->forMany($employees, $month), $month->format('F Y')),
            'khazna-attendance-'.$month->format('Y-m').'.xlsx',
        );
    }

    public function employeeExcel(Request $request, Employee $employee, MonthlySummary $summary, SessionIndexQuery $sessions): BinaryFileResponse
    {
        $this->authorize('viewAny', AttendanceSession::class);

        $month = $this->month($request->query('month'));
        $employee->loadMissing('office:id,name');

        return Excel::download(
            new AttendanceEmployeeExport($employee, $month, $summary, $sessions),
            'khazna-attendance-'.PdfRenderer::slug($employee->name, 'employee-'.$employee->id).'-'.$month->format('Y-m').'.xlsx',
        );
    }

    public function employeePdf(Request $request, Employee $employee, MonthlySummary $summary, SessionIndexQuery $sessions, PdfRenderer $pdf): Response
    {
        $this->authorize('viewAny', AttendanceSession::class);

        $month = $this->month($request->query('month'));
        $employee->loadMissing('office:id,name');

        $list = $sessions->query(new AttendanceFiltersData(
            employee: $employee->id,
            from: $month->toDateString(),
            to: $month->endOfMonth()->toDateString(),
            sort: 'startedAt',
            direction: 'asc',
        ))->get();

        return $pdf->response(
            'pdf.attendance-report',
            [
                'employee' => $employee,
                'month' => $month,
                'summary' => $summary->for($employee, $month),
                'days' => $summary->daily($employee, $month),
                'sessions' => $list,
                // The same per-day break allowance as the totals, spent session by session.
                'minutes' => WorkedTime::minutesPerSession($list),
            ],
            'Attendance report',
            'khazna-attendance-'.PdfRenderer::slug($employee->name, 'employee-'.$employee->id).'-'.$month->format('Y-m').'.pdf',
        );
    }

    /** A month as Y-m, never later than the current one. */
    private function month(mixed $value): CarbonImmutable
    {
        $current = AlgerianDate::today()->startOfMonth();

        if (! is_string($value) || preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $value) !== 1) {
            return $current;
        }

        $month = CarbonImmutable::parse($value.'-01', AlgerianDate::TZ)->startOfMonth();

        return $month->gt($current) ? $current : $month;
    }
}
