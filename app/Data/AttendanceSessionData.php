<?php

namespace App\Data;

use App\Attendance\WorkedTime;
use App\Enums\AttendanceReviewStatus;
use App\Enums\AttendanceSource;
use App\Enums\AttendanceStatus;
use App\Models\AttendanceSession;
use App\Support\AlgerianDate;
use Carbon\CarbonImmutable;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class AttendanceSessionData extends Data
{
    /**
     * @param  list<string>  $reviewReasons
     */
    public function __construct(
        public int $id,
        public int $employeeId,
        public string $employeeName,
        public int $officeId,
        public string $officeName,
        public string $startedAt,
        public ?string $endedAt,
        public string $startedAtLocal,
        public ?string $endedAtLocal,
        public AttendanceStatus $status,
        public string $statusLabel,
        public AttendanceReviewStatus $reviewStatus,
        public string $reviewLabel,
        public AttendanceSource $source,
        public string $sourceLabel,
        public int $workedMinutes,
        public string $workedFormatted,
        public ?string $report,
        public ?string $blockers,
        public bool $isOpen,
        public array $reviewReasons = [],
    ) {}

    public static function fromSession(AttendanceSession $session, ?CarbonImmutable $now = null): self
    {
        $session->loadMissing(['employee.office', 'breaks']);

        $employee = $session->employee;
        $employeeName = __('Deleted employee');
        $officeId = 0;
        $officeName = '';

        if ($employee !== null) {
            $employeeName = $employee->name;
            $officeId = $employee->office_id;
            $officeName = $employee->office->name ?? '';
        }

        $reasons = $session->hasAttribute('review_reasons')
            ? $session->getAttribute('review_reasons')
            : [];
        $workedMinutes = WorkedTime::sessionMinutes($session, $now);

        return new self(
            id: $session->id,
            employeeId: $session->employee_id,
            employeeName: $employeeName,
            officeId: $officeId,
            officeName: $officeName,
            startedAt: $session->started_at->toIso8601String(),
            endedAt: $session->ended_at?->toIso8601String(),
            startedAtLocal: $session->started_at->setTimezone(AlgerianDate::TZ)->format('Y-m-d\TH:i'),
            endedAtLocal: $session->ended_at?->setTimezone(AlgerianDate::TZ)->format('Y-m-d\TH:i'),
            status: $session->status,
            statusLabel: $session->status->label(),
            reviewStatus: $session->review_status,
            reviewLabel: $session->review_status->label(),
            source: $session->source,
            sourceLabel: $session->source->label(),
            workedMinutes: $workedMinutes,
            workedFormatted: WorkedTime::format($workedMinutes),
            report: $session->report,
            blockers: $session->blockers,
            isOpen: $session->isOpen(),
            reviewReasons: is_array($reasons) ? array_values(array_map('strval', $reasons)) : [],
        );
    }
}
