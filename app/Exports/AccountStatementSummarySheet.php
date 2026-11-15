<?php

namespace App\Exports;

use App\Models\Account;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;

class AccountStatementSummarySheet extends SpreadsheetExport implements FromArray, WithColumnFormatting
{
    /**
     * @param  array{startCents: int, totalInCents: int, totalOutCents: int, endCents: int}  $summary
     */
    public function __construct(
        private readonly Account $account,
        private readonly ?string $from,
        private readonly ?string $to,
        private readonly array $summary,
    ) {}

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
        return [
            ['Account', $this->account->name],
            ['Office', $this->account->office->name ?? ''],
            ['Type', $this->account->type->label()],
            ['Period', $this->period()],
            ['Opening balance (DA)', self::money($this->summary['startCents'])],
            ['Money in (DA)', self::money($this->summary['totalInCents'])],
            ['Money out (DA)', self::money($this->summary['totalOutCents'])],
            ['Closing balance (DA)', self::money($this->summary['endCents'])],
        ];
    }

    /** @return array<string, string> */
    public function columnFormats(): array
    {
        return ['B' => self::MONEY_FORMAT];
    }

    private function period(): string
    {
        return match (true) {
            $this->from !== null && $this->to !== null => $this->from.' to '.$this->to,
            $this->from !== null => 'From '.$this->from,
            $this->to !== null => 'Up to '.$this->to,
            default => 'All time',
        };
    }
}
