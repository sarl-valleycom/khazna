<?php

namespace App\Exports;

use Carbon\CarbonImmutable;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;

class AttendanceDaysSheet extends SpreadsheetExport implements FromArray, WithColumnFormatting
{
    /**
     * @param  array<string, array{expected: int, worked: int, note: string|null}>  $days
     */
    public function __construct(private readonly array $days) {}

    public function title(): string
    {
        return 'Days';
    }

    /** @return list<string> */
    public function headings(): array
    {
        return ['Date', 'Day', 'Expected (min)', 'Worked (min)', 'Difference (min)', 'Note'];
    }

    /** @return list<list<mixed>> */
    public function array(): array
    {
        $rows = [];

        foreach ($this->days as $date => $day) {
            $rows[] = [
                self::date($date),
                CarbonImmutable::parse($date)->format('l'),
                $day['expected'],
                $day['worked'],
                $day['worked'] - $day['expected'],
                $day['note'] === null ? '' : ucfirst($day['note']),
            ];
        }

        return $rows;
    }

    /** @return array<string, string> */
    public function columnFormats(): array
    {
        return ['A' => self::DATE_FORMAT];
    }
}
