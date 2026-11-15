<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

class CashReportCategorySheet extends SpreadsheetExport implements FromArray, WithColumnFormatting
{
    /**
     * @param  array<string, mixed>  $report
     */
    public function __construct(private readonly array $report) {}

    public function title(): string
    {
        return 'By category';
    }

    /** @return list<string> */
    public function headings(): array
    {
        return ['Direction', 'Category', ...$this->months(), 'Total (DA)'];
    }

    /** @return list<list<mixed>> */
    public function array(): array
    {
        $rows = [];

        foreach ($this->report['rows'] as $row) {
            $rows[] = [
                $row['direction'] === 'in' ? 'In' : 'Out',
                $row['category'],
                ...array_map(fn (int $cents): float => self::money($cents), array_values($row['months'])),
                self::money($row['totalCents']),
            ];
        }

        $totals = $this->report['totals'];

        foreach ([['Total', 'Money in', 'in', 'inCents'], ['Total', 'Money out', 'out', 'outCents'], ['Total', 'Cash result', 'result', 'resultCents']] as [$kind, $label, $bucket, $sum]) {
            $rows[] = [
                $kind,
                $label,
                ...array_map(fn (int $cents): float => self::money($cents), array_values($totals[$bucket])),
                self::money($totals[$sum]),
            ];
        }

        return $rows;
    }

    /** @return array<string, string> */
    public function columnFormats(): array
    {
        $formats = [];

        // Every column after the two labels holds money.
        for ($column = 3; $column <= 2 + count($this->months()) + 1; $column++) {
            $formats[Coordinate::stringFromColumnIndex($column)] = self::MONEY_FORMAT;
        }

        return $formats;
    }

    /** @return list<string> */
    private function months(): array
    {
        return array_values($this->report['months']);
    }
}
