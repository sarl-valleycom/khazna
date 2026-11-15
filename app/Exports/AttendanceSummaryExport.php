<?php

namespace App\Exports;

use App\Attendance\WorkedTime;
use App\Models\Employee;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;

/** The team's month, one row per employee: the monthly summary screen as a file. */
class AttendanceSummaryExport extends SpreadsheetExport implements FromArray
{
    /**
     * @param  Collection<int, Employee>  $employees
     * @param  array<int, array<string, int>>  $summary  From MonthlySummary::forMany().
     */
    public function __construct(
        private readonly Collection $employees,
        private readonly array $summary,
        private readonly string $monthLabel,
    ) {}

    public function title(): string
    {
        return $this->monthLabel;
    }

    /** @return list<string> */
    public function headings(): array
    {
        return ['Employee', 'Office', 'Worked (min)', 'Worked', 'Expected (min)', 'Expected', 'To make up (min)', 'To make up', 'Extra (min)', 'Extra', 'Days worked', 'Days off', 'Sessions not yet checked'];
    }

    /** @return list<list<mixed>> */
    public function array(): array
    {
        return array_values($this->employees->map(function (Employee $employee): array {
            $row = $this->summary[$employee->id];

            return [
                $employee->name,
                $employee->office->name ?? '',
                $row['worked'],
                WorkedTime::format($row['worked']),
                $row['expected'],
                WorkedTime::format($row['expected']),
                $row['to_make_up'],
                WorkedTime::format($row['to_make_up']),
                $row['extra'],
                WorkedTime::format($row['extra']),
                $row['days_worked'],
                $row['days_off'],
                $row['not_checked'],
            ];
        })->all());
    }
}
