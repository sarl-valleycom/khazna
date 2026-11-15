<?php

namespace App\Discord\Handlers\Attendance\Concerns;

use App\Attendance\AttendanceException;
use App\Models\Employee;

trait FindsEmployee
{
    protected function employeeFor(string $discordUserId): Employee
    {
        $employee = Employee::query()
            ->where('discord_user_id', $discordUserId)
            ->first();

        if ($employee === null) {
            throw AttendanceException::notLinked();
        }

        return $employee;
    }
}
