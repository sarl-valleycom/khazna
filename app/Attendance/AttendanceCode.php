<?php

namespace App\Attendance;

use App\Enums\EmployeeStatus;
use App\Models\Employee;
use App\Support\AlgerianDate;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * The personal code an employee types on the attendance page. Lookups use a
 * keyed hash (HMAC with the app key), which also keeps a code unique. The code
 * itself is kept encrypted with the app key so the owner can read it again; a
 * database copy alone does not reveal it.
 */
final class AttendanceCode
{
    public const LENGTH = 6;

    /** Makes a new code and returns it. */
    public function generate(Employee $employee): string
    {
        return DB::transaction(function () use ($employee): string {
            do {
                $code = str_pad((string) random_int(0, (10 ** self::LENGTH) - 1), self::LENGTH, '0', STR_PAD_LEFT);
                $hash = $this->hash($code);
            } while (Employee::withTrashed()->where('attendance_code_hash', $hash)->exists());

            $employee->forceFill([
                'attendance_code_hash' => $hash,
                'attendance_code_encrypted' => Crypt::encryptString($code),
                'attendance_code_set_at' => AlgerianDate::now()->utc(),
            ])->save();

            return $code;
        });
    }

    public function revoke(Employee $employee): void
    {
        $employee->forceFill([
            'attendance_code_hash' => null,
            'attendance_code_encrypted' => null,
            'attendance_code_set_at' => null,
        ])->save();
    }

    /** The current code, or null when none is set or it was made before codes could be read back. */
    public function reveal(Employee $employee): ?string
    {
        if ($employee->attendance_code_encrypted === null) {
            return null;
        }

        try {
            return Crypt::decryptString($employee->attendance_code_encrypted);
        } catch (DecryptException) {
            return null;
        }
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

    /**
     * A fingerprint of the employee's current code. The page keeps it in the
     * session, so replacing or removing the code ends every sign-in made with
     * the old one.
     */
    public function fingerprint(Employee $employee): ?string
    {
        return $employee->attendance_code_hash === null
            ? null
            : hash('sha256', $employee->attendance_code_hash);
    }

    private function hash(string $code): string
    {
        return hash_hmac('sha256', $code, (string) config('app.key'));
    }
}
