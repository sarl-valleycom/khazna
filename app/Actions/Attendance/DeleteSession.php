<?php

namespace App\Actions\Attendance;

use App\Actions\Attendance\Concerns\LocksEmployee;
use App\Actions\Attendance\Concerns\RejectsPendingCorrections;
use App\Models\AttendanceSession;
use App\Support\AuditContext;
use Illuminate\Support\Facades\DB;

/**
 * Deleting a session also soft-deletes its breaks, so a restored session never
 * comes back with orphaned break rows, and closes its pending correction
 * requests so they do not linger in the inbox without a session.
 */
class DeleteSession
{
    use LocksEmployee;
    use RejectsPendingCorrections;

    public function handle(AttendanceSession $session, string $reason): void
    {
        DB::transaction(function () use ($session, $reason): void {
            $this->lockEmployee($session->employee_id);

            $locked = AttendanceSession::query()
                ->whereKey($session->id)
                ->lockForUpdate()
                ->firstOrFail();

            app(AuditContext::class)->withReason($reason, function () use ($locked): void {
                $this->rejectPendingCorrections($locked);
                $locked->breaks()->delete();
                $locked->delete();
            });
        });
    }
}
