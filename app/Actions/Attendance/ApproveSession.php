<?php

namespace App\Actions\Attendance;

use App\Actions\Attendance\Concerns\LocksEmployee;
use App\Enums\AttendanceReviewStatus;
use App\Models\AttendanceSession;
use App\Support\AuditContext;
use Illuminate\Support\Facades\DB;

class ApproveSession
{
    use LocksEmployee;

    public function handle(AttendanceSession $session, string $reason): AttendanceSession
    {
        return DB::transaction(function () use ($session, $reason): AttendanceSession {
            $this->lockEmployee($session->employee_id);

            $locked = AttendanceSession::query()
                ->whereKey($session->id)
                ->lockForUpdate()
                ->firstOrFail();

            app(AuditContext::class)->withReason($reason, fn () => $locked->update([
                'review_status' => AttendanceReviewStatus::Approved,
            ]));

            return $locked->refresh()->load('breaks');
        });
    }
}
