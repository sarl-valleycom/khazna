<?php

namespace App\Data;

use App\Enums\EmployeeStatus;
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
        );
    }
}
