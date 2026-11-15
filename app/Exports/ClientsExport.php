<?php

namespace App\Exports;

use App\Data\ClientFiltersData;
use App\Models\Client;
use App\Models\User;
use App\Queries\ClientIndexQuery;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithMapping;

/** The clients list as shown, with its filters and the viewer's visibility. */
class ClientsExport extends SpreadsheetExport implements FromQuery, WithColumnFormatting, WithMapping
{
    public function __construct(private readonly User $user, private readonly ClientFiltersData $filters, private readonly ClientIndexQuery $clients) {}

    /** @return Builder<Client> */
    public function query(): Builder
    {
        return $this->clients->query($this->user, $this->filters);
    }

    public function title(): string
    {
        return 'Clients';
    }

    /** @return list<string> */
    public function headings(): array
    {
        return ['ID', 'Name', 'Contact', 'Phone', 'Email', 'Address', 'Notes', 'Account manager', 'Office'];
    }

    /**
     * @param  Client  $row
     * @return list<mixed>
     */
    public function map($row): array
    {
        return [
            $row->id,
            $row->name,
            $row->contact,
            $row->phone,
            $row->email,
            $row->address,
            $row->notes,
            $row->accountManager->name ?? '',
            $row->office->name ?? '',
        ];
    }

    /** @return array<string, string> */
    public function columnFormats(): array
    {
        return ['D' => '@'];
    }
}
