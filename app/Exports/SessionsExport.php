<?php

namespace App\Exports;

use App\Data\AttendanceFiltersData;
use App\Data\AttendanceSessionData;
use App\Models\AttendanceSession;
use App\Queries\SessionIndexQuery;
use App\Support\AlgerianDate;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithMapping;

/** The sessions list as shown, with its filters. The worked time is the screen's own number. */
class SessionsExport extends SpreadsheetExport implements FromQuery, WithColumnFormatting, WithMapping
{
    public function __construct(private readonly AttendanceFiltersData $filters, private readonly SessionIndexQuery $sessions) {}

    /** @return Builder<AttendanceSession> */
    public function query(): Builder
    {
        return $this->sessions->query($this->filters);
    }

    public function title(): string
    {
        return 'Sessions';
    }

    /** @return list<string> */
    public function headings(): array
    {
        return ['ID', 'Employee', 'Office', 'Date', 'Start', 'End', 'Status', 'Review', 'Source', 'Worked (min)', 'Worked', 'Report', 'Blockers'];
    }

    /**
     * @param  AttendanceSession  $row
     * @return list<mixed>
     */
    public function map($row): array
    {
        $data = AttendanceSessionData::fromSession($row);

        return [
            $data->id,
            $data->employeeName,
            $data->officeName,
            self::date(AlgerianDate::toAlgiers($row->started_at)->toDateString()),
            self::time($row->started_at),
            self::time($row->ended_at),
            $data->statusLabel,
            $data->reviewLabel,
            $data->sourceLabel,
            $data->workedMinutes,
            $data->workedFormatted,
            $data->report,
            $data->blockers,
        ];
    }

    /** @return array<string, string> */
    public function columnFormats(): array
    {
        return ['D' => self::DATE_FORMAT];
    }
}
