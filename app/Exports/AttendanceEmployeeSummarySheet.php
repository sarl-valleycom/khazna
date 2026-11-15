<?php

namespace App\Exports;

use App\Attendance\WorkedTime;
use App\Models\Employee;
use Carbon\CarbonImmutable;
use Maatwebsite\Excel\Concerns\FromArray;

class AttendanceEmployeeSummarySheet extends SpreadsheetExport implements FromArray
{
    /**
     * @param  array<string, int>  $summary
     */
    public function __construct(private readonly Employee $employee, private readonly CarbonImmutable $month, private readonly array $summary) {}

    public function title(): string
    {
        return 'Summary';
    }

    /** @return list<string> */
    public function headings(): array
    {
        return ['Item', 'Value'];
    }

    /** @return list<list<mixed>> */
    public function array(): array
    {
        $s = $this->summary;

        return [
            ['Employee', $this->employee->name],
            ['Office', $this->employee->office->name ?? ''],
            ['Month', $this->month->format('F Y')],
            ['Worked', WorkedTime::format($s['worked'])],
            ['Worked (min)', $s['worked']],
            ['Expected', WorkedTime::format($s['expected'])],
            ['Expected (min)', $s['expected']],
            ['To make up', WorkedTime::format($s['to_make_up'])],
            ['To make up (min)', $s['to_make_up']],
            ['Extra', WorkedTime::format($s['extra'])],
            ['Extra (min)', $s['extra']],
            ['Days worked', $s['days_worked']],
            ['Days off (holidays and leave)', $s['days_off']],
            ['Sessions closed automatically and not yet checked', $s['not_checked']],
        ];
    }
}
