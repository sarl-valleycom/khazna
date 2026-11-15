<?php

namespace App\Data;

use App\Enums\EmployeeStatus;
use App\Enums\EmploymentType;
use App\Models\Employee;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * An employee without any money on it. This is the only shape a user without
 * the salary permission can ever receive.
 */
#[TypeScript]
class EmployeeData extends Data
{
    /**
     * @param  list<int>  $workDays
     */
    public function __construct(
        public int $id,
        public string $name,
        public ?string $phone,
        public ?string $title,
        public ?string $hireDate,
        public EmployeeStatus $status,
        public string $statusLabel,
        public int $officeId,
        public string $officeName,
        public EmploymentType $contractType,
        public string $contractTypeLabel,
        public int $expectedMinutesPerDay,
        public array $workDays,
        public ?string $scheduleStart,
        public ?string $scheduleEnd,
        public bool $hasAttendanceCode = false,
        public ?string $attendanceCodeSetAt = null,
    ) {}

    public static function fromEmployee(Employee $employee): self
    {
        return new self(
            id: $employee->id,
            name: $employee->name,
            phone: $employee->phone,
            title: $employee->title,
            hireDate: $employee->hire_date?->toDateString(),
            status: $employee->status,
            statusLabel: $employee->status->label(),
            officeId: $employee->office_id,
            officeName: $employee->office->name ?? '',
            contractType: $employee->contract_type,
            contractTypeLabel: $employee->contract_type->label(),
            expectedMinutesPerDay: $employee->expected_minutes_per_day,
            workDays: $employee->workDays(),
            scheduleStart: $employee->schedule_start,
            scheduleEnd: $employee->schedule_end,
            hasAttendanceCode: $employee->hasAttendanceCode(),
            attendanceCodeSetAt: $employee->attendance_code_set_at?->toIso8601String(),
        );
    }
}
