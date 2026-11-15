<?php

namespace App\Data;

use App\Models\Office;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class OfficeAttendanceChannelData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public ?string $attendanceChannelId,
    ) {}

    public static function fromOffice(Office $office): self
    {
        return new self(
            id: $office->id,
            name: $office->name,
            attendanceChannelId: $office->attendance_channel_id,
        );
    }
}
