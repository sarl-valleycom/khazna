<?php

namespace App\Exports;

use App\Data\ContractFiltersData;
use App\Models\Contract;
use App\Models\User;
use App\Queries\ContractIndexQuery;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithMapping;

/** The contracts list as shown, with its filters and the viewer's visibility. */
class ContractsExport extends SpreadsheetExport implements FromQuery, WithColumnFormatting, WithMapping
{
    public function __construct(private readonly User $user, private readonly ContractFiltersData $filters, private readonly ContractIndexQuery $contracts) {}

    /** @return Builder<Contract> */
    public function query(): Builder
    {
        return $this->contracts->query($this->user, $this->filters);
    }

    public function title(): string
    {
        return 'Contracts';
    }

    /** @return list<string> */
    public function headings(): array
    {
        return ['ID', 'Party', 'Office', 'Type', 'Direction', 'Amount (DA)', 'Cycle', 'Starts on', 'Ends on', 'Status', 'Auto-renew', 'Category', 'Notes'];
    }

    /**
     * @param  Contract  $row
     * @return list<mixed>
     */
    public function map($row): array
    {
        return [
            $row->id,
            $row->partyName(),
            $row->office->name ?? '',
            $row->type->label(),
            $row->direction->label(),
            self::money($row->amount_cents),
            $row->cycle->label(),
            self::date($row->starts_on->toDateString()),
            self::date($row->ends_on?->toDateString()),
            $row->status->label(),
            $row->auto_renew ? 'Yes' : 'No',
            $row->category->name ?? '',
            $row->notes,
        ];
    }

    /** @return array<string, string> */
    public function columnFormats(): array
    {
        return [
            'F' => self::MONEY_FORMAT,
            'H' => self::DATE_FORMAT,
            'I' => self::DATE_FORMAT,
        ];
    }
}
