<?php

namespace App\Data;

use App\Models\Holiday;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class HolidayData extends Data
{
    public function __construct(
        public int $id,
        public string $date,
        public string $weekday,
        public string $label,
    ) {}

    public static function fromHoliday(Holiday $holiday): self
    {
        return new self(
            id: $holiday->id,
            date: $holiday->date->toDateString(),
            weekday: $holiday->date->format('l'),
            label: $holiday->label,
        );
    }
}
