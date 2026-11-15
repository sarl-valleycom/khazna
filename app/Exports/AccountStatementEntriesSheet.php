<?php

namespace App\Exports;

use App\Enums\Direction;
use App\Models\Account;
use App\Models\AccountEntry;
use App\Models\User;
use App\Queries\AccountStatementQuery;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithMapping;

class AccountStatementEntriesSheet extends SpreadsheetExport implements FromQuery, WithColumnFormatting, WithMapping
{
    public function __construct(
        private readonly Account $account,
        private readonly User $viewer,
        private readonly ?string $from,
        private readonly ?string $to,
        private readonly int $startCents,
        private readonly AccountStatementQuery $statement,
    ) {}

    /** @return Builder<AccountEntry> */
    public function query(): Builder
    {
        return $this->statement->entries($this->account, $this->viewer, $this->from, $this->to, $this->startCents);
    }

    public function title(): string
    {
        return 'Entries';
    }

    /** @return list<string> */
    public function headings(): array
    {
        return ['Date', 'Description', 'Money in (DA)', 'Money out (DA)', 'Balance (DA)'];
    }

    /**
     * @param  AccountEntry  $row
     * @return list<mixed>
     */
    public function map($row): array
    {
        return [
            self::date($row->entry_date->toDateString()),
            $row->description ?? '',
            $row->direction === Direction::In ? self::money($row->amount_cents) : null,
            $row->direction === Direction::Out ? self::money($row->amount_cents) : null,
            self::money((int) $row->running_cents),
        ];
    }

    /** @return array<string, string> */
    public function columnFormats(): array
    {
        return [
            'A' => self::DATE_FORMAT,
            'C' => self::MONEY_FORMAT,
            'D' => self::MONEY_FORMAT,
            'E' => self::MONEY_FORMAT,
        ];
    }
}
