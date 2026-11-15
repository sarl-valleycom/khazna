<?php

namespace App\Data;

use App\Attendance\WorkedTime;
use App\Enums\AttendanceStatus;
use App\Models\AttendanceSession;
use App\Support\AlgerianDate;
use Carbon\CarbonImmutable;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class WorkSessionData extends Data
{
    public function __construct(
        public int $id,
        public string $dateLabel,
        public string $startLabel,
        public ?string $endLabel,
        public int $workedMinutes,
        public string $workedFormatted,
        public bool $autoClosed,
        public ?string $report,
        public ?string $blockers,
    ) {}

    public static function fromSession(AttendanceSession $session, CarbonImmutable $now): self
    {
        $start = $session->started_at->setTimezone(AlgerianDate::TZ);
        $minutes = WorkedTime::sessionMinutes($session, $now);

        return new self(
            id: $session->id,
            dateLabel: $start->format('D d M'),
            startLabel: $start->format('H:i'),
            endLabel: $session->ended_at?->setTimezone(AlgerianDate::TZ)->format('H:i'),
            workedMinutes: $minutes,
            workedFormatted: WorkedTime::format($minutes),
            autoClosed: $session->status === AttendanceStatus::AutoClosed,
            report: $session->report,
            blockers: $session->blockers,
        );
    }
}
