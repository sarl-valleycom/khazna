<?php

namespace App\Data;

use App\Models\LeaveRequest;
use Carbon\CarbonImmutable;
use LogicException;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class LeaveEntryData extends Data
{
    public function __construct(
        public int $id,
        public int $employeeId,
        public string $employeeName,
        public string $officeName,
        public string $type,
        public string $typeLabel,
        public string $from,
        public string $to,
        public int $workDays,
        public ?string $reason,
    ) {}

    public static function fromLeave(LeaveRequest $leave): self
    {
        // The foreign key forbids deleting an employee who has leave, so this is never missing.
        $employee = $leave->employee ?? throw new LogicException('Leave without an employee.');
        $workDays = 0;

        for ($day = CarbonImmutable::parse($leave->from->toDateString()); $day->lte($leave->to); $day = $day->addDay()) {
            if ($employee->worksOn($day)) {
                $workDays++;
            }
        }

        return new self(
            id: $leave->id,
            employeeId: $leave->employee_id,
            employeeName: $employee->name,
            officeName: $employee->office->name ?? '',
            type: $leave->type->value,
            typeLabel: $leave->type->label(),
            from: $leave->from->toDateString(),
            to: $leave->to->toDateString(),
            workDays: $workDays,
            reason: $leave->reason,
        );
    }
}
