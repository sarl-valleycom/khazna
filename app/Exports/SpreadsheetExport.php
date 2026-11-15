<?php

namespace App\Exports;

use App\Support\AlgerianDate;
use Carbon\CarbonInterface;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithFreezePane;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Shared\StringHelper;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * What every Khazna spreadsheet shares: a bold header row that stays in view,
 * columns sized to their content, dates as real dates, money as numbers in DA,
 * and text that stays text.
 *
 * A zero is written as a zero: without strict null comparison the library
 * turns 0 into an empty cell, and a paid amount of 0.00 would look missing.
 *
 * Text is never interpreted. A client named "=HYPERLINK(...)" is stored as the
 * words it is, not as a formula, and a phone number keeps its leading zero.
 */
abstract class SpreadsheetExport extends DefaultValueBinder implements ShouldAutoSize, WithCustomValueBinder, WithFreezePane, WithHeadings, WithStrictNullComparison, WithStyles, WithTitle
{
    public const DATE_FORMAT = NumberFormat::FORMAT_DATE_YYYYMMDD;

    public const MONEY_FORMAT = '#,##0.00';

    public function bindValue(Cell $cell, mixed $value): bool
    {
        if (is_string($value)) {
            $cell->setValueExplicit(StringHelper::sanitizeUTF8($value), DataType::TYPE_STRING);

            return true;
        }

        return parent::bindValue($cell, $value);
    }

    public function freezePane(): string
    {
        return 'A2';
    }

    /**
     * @return array<int|string, array<string, mixed>>
     */
    public function styles(Worksheet $sheet): array
    {
        return [1 => ['font' => ['bold' => true]]];
    }

    /** A calendar date (Y-m-d) as an Excel date, or an empty cell. */
    protected static function date(?string $date): ?float
    {
        if ($date === null || $date === '') {
            return null;
        }

        $serial = Date::stringToExcel($date);

        return $serial === false ? null : $serial;
    }

    /** Whole centimes as DA with two decimals. The spreadsheet only displays it; Khazna's totals stay in centimes. */
    protected static function money(int $cents): float
    {
        return round($cents / 100, 2);
    }

    /** An Algiers wall-clock time (H:i) from a UTC instant, or an empty cell. */
    protected static function time(?CarbonInterface $at): ?string
    {
        return $at === null ? null : AlgerianDate::toAlgiers($at)->format('H:i');
    }
}
