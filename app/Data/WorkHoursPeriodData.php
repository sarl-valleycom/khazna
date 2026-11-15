<?php

namespace App\Data;

use App\Attendance\WorkedTime;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class WorkHoursPeriodData extends Data
{
    public function __construct(
        public int $expectedMinutes,
        public int $workedMinutes,
        public int $remainingMinutes,
        public int $extraMinutes,
        public int $behindBeforeTodayMinutes,
        public string $expectedFormatted,
        public string $workedFormatted,
        public string $remainingFormatted,
        public string $extraFormatted,
        public string $behindBeforeTodayFormatted,
    ) {}

    /**
     * @param  array<string, int>  $period
     */
    public static function fromPeriod(array $period): self
    {
        return new self(
            expectedMinutes: $period['expected'],
            workedMinutes: $period['worked'],
            remainingMinutes: $period['remaining'],
            extraMinutes: $period['extra'],
            behindBeforeTodayMinutes: $period['behind_before_today'],
            expectedFormatted: WorkedTime::format($period['expected']),
            workedFormatted: WorkedTime::format($period['worked']),
            remainingFormatted: WorkedTime::format($period['remaining']),
            extraFormatted: WorkedTime::format($period['extra']),
            behindBeforeTodayFormatted: WorkedTime::format($period['behind_before_today']),
        );
    }
}
