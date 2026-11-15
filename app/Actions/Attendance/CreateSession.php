<?php

namespace App\Actions\Attendance;

use App\Actions\Attendance\Concerns\LocksEmployee;
use App\Enums\AttendanceReviewStatus;
use App\Enums\AttendanceSource;
use App\Enums\AttendanceStatus;
use App\Models\AttendanceSession;
use App\Models\Employee;
use App\Models\User;
use App\Support\AuditContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * A session entered by the owner, for a day Discord did not record. A reason is
 * required because this is a manual attendance record.
 */
class CreateSession
{
    use LocksEmployee;

    public function handle(
        Employee $employee,
        CarbonImmutable $startedAt,
        ?CarbonImmutable $endedAt,
        ?string $report,
        ?string $blockers,
        string $reason,
        User $actor,
    ): AttendanceSession {
        $startedAt = $startedAt->utc();
        $endedAt = $endedAt?->utc();

        return DB::transaction(function () use ($employee, $startedAt, $endedAt, $report, $blockers, $reason, $actor): AttendanceSession {
            $locked = $this->lockEmployee($employee->id);

            if ($endedAt === null && AttendanceSession::query()
                ->where('employee_id', $locked->id)
                ->open()
                ->lockForUpdate()
                ->exists()) {
                throw ValidationException::withMessages([
                    'ended_at' => __('This employee already has an open session.'),
                ]);
            }

            $session = app(AuditContext::class)->withReason($reason, fn (): AttendanceSession => AttendanceSession::query()->create([
                'employee_id' => $locked->id,
                'started_at' => $startedAt,
                'ended_at' => $endedAt,
                'status' => $endedAt === null ? AttendanceStatus::Open : AttendanceStatus::Closed,
                'review_status' => $endedAt === null ? AttendanceReviewStatus::Ok : AttendanceReviewStatus::Approved,
                'report' => self::text($report),
                'blockers' => self::text($blockers),
                'source' => AttendanceSource::Admin,
                'closed_by' => $endedAt === null ? null : $actor->id,
            ]));

            return $session->refresh()->load('breaks');
        });
    }

    private static function text(?string $value): ?string
    {
        $value = $value === null ? null : trim($value);

        return $value === '' ? null : $value;
    }
}
