<?php

namespace App\Data;

use App\Models\Employee;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class AttendanceEmployeeOptionData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public int $officeId,
        public string $officeName,
    ) {}

    public static function fromEmployee(Employee $employee): self
    {
        return new self(
            id: $employee->id,
            name: $employee->name,
            officeId: $employee->office_id,
            officeName: $employee->office->name ?? '',
        );
    }
}
