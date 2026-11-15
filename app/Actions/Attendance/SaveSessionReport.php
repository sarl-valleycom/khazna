<?php

namespace App\Actions\Attendance;

use App\Actions\Attendance\Concerns\LocksEmployee;
use App\Attendance\AttendanceException;
use App\Enums\AttendanceStatus;
use App\Models\AttendanceSession;
use Illuminate\Support\Facades\DB;

/**
 * Saves the end-of-day report on a session the employee already checked out
 * of. The session was closed by /check-out, so a dismissed modal never leaves
 * an open session behind.
 */
class SaveSessionReport
{
    use LocksEmployee;

    public function handle(AttendanceSession $session, string $report, ?string $blockers): AttendanceSession
    {
        $report = trim($report);
        $blockers = $blockers === null ? null : trim($blockers);
        $blockers = $blockers === '' ? null : $blockers;

        if ($report === '') {
            throw AttendanceException::reportRequired();
        }

        return DB::transaction(function () use ($session, $report, $blockers): AttendanceSession {
            $this->lockEmployee($session->employee_id);

            $locked = AttendanceSession::query()
                ->whereKey($session->id)
                ->lockForUpdate()
                ->first();

            if ($locked === null) {
                throw AttendanceException::sessionNotFound();
            }

            if ($locked->ended_at === null || $locked->status === AttendanceStatus::Open) {
                throw AttendanceException::notCheckedOut();
            }

            $locked->update([
                'report' => $report,
                'blockers' => $blockers,
            ]);

            return $locked->refresh();
        });
    }
}
