<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;

class CashReportOfficesSheet extends SpreadsheetExport implements FromArray, WithColumnFormatting
{
    /**
     * @param  array<string, mixed>  $report
     */
    public function __construct(private readonly array $report) {}

    public function title(): string
    {
        return 'By office';
    }

    /** @return list<string> */
    public function headings(): array
    {
        return ['Office', 'Money in (DA)', 'Money out (DA)', 'Cash result (DA)', 'Balance today (DA)', 'Transfers sent (DA)', 'Transfers received (DA)'];
    }

    /** @return list<list<mixed>> */
    public function array(): array
    {
        return array_map(fn (array $office): array => [
            $office['officeName'],
            self::money($office['inCents']),
            self::money($office['outCents']),
            self::money($office['resultCents']),
            self::money($office['balanceCents']),
            self::money($office['sentCents']),
            self::money($office['receivedCents']),
        ], array_values($this->report['offices']));
    }

    /** @return array<string, string> */
    public function columnFormats(): array
    {
        return ['B' => self::MONEY_FORMAT, 'C' => self::MONEY_FORMAT, 'D' => self::MONEY_FORMAT, 'E' => self::MONEY_FORMAT, 'F' => self::MONEY_FORMAT, 'G' => self::MONEY_FORMAT];
    }
}
