<?php

namespace App\Attendance;

use App\Enums\EmployeeStatus;
use App\Models\Employee;
use App\Support\AlgerianDate;
use Illuminate\Support\Facades\DB;

/**
 * The personal code an employee types on the attendance page. Only a keyed
 * hash is stored (HMAC with the app key), so a database copy alone does not
 * reveal the codes, and the same hash makes a code unique and easy to look up.
 */
final class AttendanceCode
{
    public const LENGTH = 6;

    /** Returns the new code. It is shown once and cannot be read back. */
    public function generate(Employee $employee): string
    {
        return DB::transaction(function () use ($employee): string {
            do {
                $code = str_pad((string) random_int(0, (10 ** self::LENGTH) - 1), self::LENGTH, '0', STR_PAD_LEFT);
                $hash = $this->hash($code);
            } while (Employee::withTrashed()->where('attendance_code_hash', $hash)->exists());

            $employee->forceFill([
                'attendance_code_hash' => $hash,
                'attendance_code_set_at' => AlgerianDate::now()->utc(),
            ])->save();

            return $code;
        });
    }

    public function revoke(Employee $employee): void
    {
        $employee->forceFill([
            'attendance_code_hash' => null,
            'attendance_code_set_at' => null,
        ])->save();
    }

    /** The active employee who owns this code, if any. */
    public function find(string $input): ?Employee
    {
        $code = preg_replace('/\D/', '', $input) ?? '';

        if (strlen($code) !== self::LENGTH) {
            return null;
        }

        return Employee::query()
            ->where('attendance_code_hash', $this->hash($code))
            ->where('status', EmployeeStatus::Active)
            ->first();
    }

    private function hash(string $code): string
    {
        return hash_hmac('sha256', $code, (string) config('app.key'));
    }
}
