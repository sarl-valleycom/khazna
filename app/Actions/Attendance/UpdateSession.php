<?php

namespace App\Actions\Attendance;

use App\Actions\Attendance\Concerns\LocksEmployee;
use App\Actions\Attendance\Concerns\RejectsPendingCorrections;
use App\Enums\AttendanceReviewStatus;
use App\Enums\AttendanceStatus;
use App\Models\AttendanceSession;
use App\Models\User;
use App\Support\AuditContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The owner fixes a session by hand. The session keeps its source (web or
 * admin); an auto-closed row stays auto-closed, but the change marks it reviewed.
 * Changing the start or end closes the session's pending correction requests.
 */
class UpdateSession
{
    use LocksEmployee;
    use RejectsPendingCorrections;

    public function handle(
        AttendanceSession $session,
        CarbonImmutable $startedAt,
        ?CarbonImmutable $endedAt,
        ?string $report,
        ?string $blockers,
        string $reason,
        User $actor,
    ): AttendanceSession {
        $startedAt = $startedAt->utc();
        $endedAt = $endedAt?->utc();

        return DB::transaction(function () use ($session, $startedAt, $endedAt, $report, $blockers, $reason, $actor): AttendanceSession {
            $this->lockEmployee($session->employee_id);

            $locked = AttendanceSession::query()
                ->whereKey($session->id)
                ->lockForUpdate()
                ->firstOrFail();

            // The edit form shows times to the minute. When a time did not move
            // at that precision, keep the stored seconds: saving a report must
            // not rewrite the times or count as a time change.
            $startedAt = self::keepStoredSeconds($locked->started_at, $startedAt);
            $endedAt = $endedAt === null ? null : self::keepStoredSeconds($locked->ended_at, $endedAt);

            if ($endedAt === null && AttendanceSession::query()
                ->where('employee_id', $locked->employee_id)
                ->whereKeyNot($locked->id)
                ->open()
                ->lockForUpdate()
                ->exists()) {
                throw ValidationException::withMessages([
                    'ended_at' => __('This employee already has an open session.'),
                ]);
            }

            if ($endedAt !== null) {
                $locked->breaks()
                    ->open()
                    ->lockForUpdate()
                    ->get()
                    ->each(fn ($break) => $break->update([
                        'ended_at' => $endedAt->lt($break->started_at) ? $break->started_at : $endedAt,
                    ]));
            }

            $status = match (true) {
                $endedAt === null => AttendanceStatus::Open,
                $locked->status === AttendanceStatus::AutoClosed => AttendanceStatus::AutoClosed,
                default => AttendanceStatus::Closed,
            };

            $previousEnd = $locked->ended_at;
            $timesChanged = ! $locked->started_at->equalTo($startedAt)
                || ($previousEnd === null) !== ($endedAt === null)
                || ($previousEnd !== null && $endedAt !== null && ! $previousEnd->equalTo($endedAt));

            app(AuditContext::class)->withReason($reason, fn () => $locked->update([
                'started_at' => $startedAt,
                'ended_at' => $endedAt,
                'status' => $status,
                'review_status' => $endedAt === null ? AttendanceReviewStatus::Ok : AttendanceReviewStatus::Approved,
                'report' => self::text($report),
                'blockers' => self::text($blockers),
                'closed_by' => $endedAt === null ? null : ($locked->closed_by ?? $actor->id),
            ]));

            if ($timesChanged) {
                // Approving the old proposal later would overwrite this edit.
                $this->rejectPendingCorrections($locked, $actor);
            }

            return $locked->refresh()->load('breaks');
        });
    }

    private static function keepStoredSeconds(?CarbonImmutable $stored, CarbonImmutable $submitted): CarbonImmutable
    {
        return $stored !== null && $stored->format('Y-m-d H:i') === $submitted->format('Y-m-d H:i')
            ? $stored
            : $submitted;
    }

    private static function text(?string $value): ?string
    {
        $value = $value === null ? null : trim($value);

        return $value === '' ? null : $value;
    }
}
