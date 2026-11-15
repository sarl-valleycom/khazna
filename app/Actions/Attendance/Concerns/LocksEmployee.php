<?php

namespace App\Actions\Attendance\Concerns;

use App\Models\Employee;

trait LocksEmployee
{
    protected function lockEmployee(int $employeeId): Employee
    {
        return Employee::query()->whereKey($employeeId)->lockForUpdate()->firstOrFail();
    }
}
