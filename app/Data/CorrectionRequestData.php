<?php

namespace App\Data;

use App\Enums\CorrectionStatus;
use App\Models\CorrectionRequest;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class CorrectionRequestData extends Data
{
    public function __construct(
        public int $id,
        public int $sessionId,
        public int $employeeId,
        public string $employeeName,
        public string $officeName,
        public string $startedAt,
        public ?string $endedAt,
        public string $proposedEndedAt,
        public ?string $note,
        public string $createdAt,
        public CorrectionStatus $status,
        public string $statusLabel,
    ) {}

    public static function fromRequest(CorrectionRequest $request): self
    {
        $request->loadMissing(['employee.office', 'session']);

        $employee = $request->employee;
        $session = $request->session;
        $startedAt = $session?->started_at;
        $employeeName = __('Deleted employee');
        $officeName = '';

        if ($employee !== null) {
            $employeeName = $employee->name;
            $officeName = $employee->office->name ?? '';
        }

        return new self(
            id: $request->id,
            sessionId: $request->session_id,
            employeeId: $request->employee_id,
            employeeName: $employeeName,
            officeName: $officeName,
            startedAt: $startedAt?->toIso8601String() ?? '',
            endedAt: $session?->ended_at?->toIso8601String(),
            proposedEndedAt: $request->proposed_ended_at->toIso8601String(),
            note: $request->note,
            createdAt: $request->created_at?->toIso8601String() ?? '',
            status: $request->status,
            statusLabel: $request->status->label(),
        );
    }
}
