<?php

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class AttendancePresenceData extends Data
{
    public function __construct(
        public int $employeeId,
        public string $name,
        public string $officeName,
        public string $state,
        public string $stateLabel,
        public ?string $since,
        public string $sinceLabel,
        public int $todayMinutes,
        public string $todayFormatted,
    ) {}
}
