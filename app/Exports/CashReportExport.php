<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * The cash report as a workbook: money in and out by category and month, and the
 * result per office. The numbers are the array CashReportQuery builds for the
 * screen, so the file and the page can never disagree, and payroll is left out
 * for anyone without the salary permission exactly as on screen.
 */
class CashReportExport implements Export, WithMultipleSheets
{
    /**
     * @param  array<string, mixed>  $report  The result of CashReportQuery::handle().
     */
    public function __construct(private readonly array $report) {}

    /** @return list<Export> */
    public function sheets(): array
    {
        return [
            new CashReportCategorySheet($this->report),
            new CashReportOfficesSheet($this->report),
        ];
    }
}
