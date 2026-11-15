<?php

namespace App\Exports;

use App\Data\TriageFiltersData;
use App\Models\Due;
use App\Models\User;
use App\Queries\DueTriageQuery;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * The dues of the chosen triage tab, with its filters. It walks the very query
 * the tab uses, so the viewer's visibility applies: salary dues exist only for
 * people who may see salaries, and an office or account-manager scope holds.
 */
class DuesExport extends SpreadsheetExport implements FromQuery, WithColumnFormatting, WithMapping
{
    public function __construct(
        private readonly User $user,
        private readonly TriageFiltersData $filters,
        private readonly CarbonImmutable $today,
        private readonly DueTriageQuery $triage,
    ) {}

    /** @return Builder<Due> */
    public function query(): Builder
    {
        return $this->triage->rowsQuery($this->user, $this->filters, $this->today)->without(['postponements', 'postponements.user']);
    }

    public function title(): string
    {
        return 'Dues';
    }

    /** @return list<string> */
    public function headings(): array
    {
        return ['ID', 'Party', 'Party type', 'Office', 'Kind', 'Direction', 'Description', 'Period', 'Due on', 'Amount due (DA)', 'Paid (DA)', 'Remaining (DA)', 'Credit (DA)', 'Status'];
    }

    /**
     * @param  Due  $row
     * @return list<mixed>
     */
    public function map($row): array
    {
        return [
            $row->id,
            $row->partyName(),
            $row->partyType(),
            $row->office->name ?? '',
            $row->kind->label(),
            $row->direction->label(),
            $row->description,
            self::date($row->period?->toDateString()),
            self::date($row->due_on?->toDateString()),
            self::money($row->amount_due_cents),
            self::money($row->amount_paid_cents),
            self::money($row->remainingCents()),
            self::money($row->creditCents()),
            $row->isOverdue() ? 'Overdue' : $row->status->label(),
        ];
    }

    /** @return array<string, string> */
    public function columnFormats(): array
    {
        return [
            'H' => self::DATE_FORMAT,
            'I' => self::DATE_FORMAT,
            'J' => self::MONEY_FORMAT,
            'K' => self::MONEY_FORMAT,
            'L' => self::MONEY_FORMAT,
            'M' => self::MONEY_FORMAT,
        ];
    }
}
