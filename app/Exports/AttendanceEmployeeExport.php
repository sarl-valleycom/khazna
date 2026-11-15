<?php

namespace App\Exports;

use App\Attendance\MonthlySummary;
use App\Data\AttendanceFiltersData;
use App\Models\Employee;
use App\Queries\SessionIndexQuery;
use Carbon\CarbonImmutable;
use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * One employee's month as a workbook: the summary, the days (expected and worked
 * against each other) and every session. The sessions come from the same query
 * as the sessions screen.
 */
class AttendanceEmployeeExport implements Export, WithMultipleSheets
{
    public function __construct(
        private readonly Employee $employee,
        private readonly CarbonImmutable $month,
        private readonly MonthlySummary $summary,
        private readonly SessionIndexQuery $sessions,
    ) {}

    /** @return list<Export> */
    public function sheets(): array
    {
        $filters = new AttendanceFiltersData(
            employee: $this->employee->id,
            from: $this->month->startOfMonth()->toDateString(),
            to: $this->month->endOfMonth()->toDateString(),
            sort: 'startedAt',
            direction: 'asc',
        );

        return [
            new AttendanceEmployeeSummarySheet($this->employee, $this->month, $this->summary->for($this->employee, $this->month)),
            new AttendanceDaysSheet($this->summary->daily($this->employee, $this->month)),
            new SessionsExport($filters, $this->sessions),
        ];
    }
}
