<?php

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class WorkDashboardData extends Data
{
    /**
     * @param  string  $state  offline, working or away
     * @param  list<WorkSessionData>  $recent
     * @param  array{id: int, label: string, endTime: string}|null  $correction  an auto-closed session the employee can fix
     * @param  array{id: int, label: string}|null  $reportPrompt  a finished session still without a report
     */
    public function __construct(
        public string $name,
        public string $state,
        public string $serverNow,
        public ?string $sessionStartedAt,
        public ?string $breakStartedAt,
        public int $todayWorkedMinutes,
        public WorkHoursPeriodData $today,
        public WorkHoursPeriodData $week,
        public WorkHoursPeriodData $month,
        public array $recent,
        public ?array $correction,
        public ?array $reportPrompt,
    ) {}
}
