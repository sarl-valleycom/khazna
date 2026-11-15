<?php

namespace App\Actions\Attendance;

use App\Actions\Attendance\Concerns\LocksEmployee;
use App\Attendance\AttendanceException;
use App\Enums\AttendanceStatus;
use App\Models\AttendanceSession;
use App\Support\AlgerianDate;
use Illuminate\Support\Facades\DB;

/**
 * Saves the end-of-day report on a session the employee already checked out
 * of. The session was closed by /check-out, so a dismissed modal never leaves
 * an open session behind.
 */
class SaveSessionReport
{
    use LocksEmployee;

    /** How long after the check-out an employee can still write the report. */
    public const WINDOW_HOURS = 36;

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

            // An employee writes the report once, soon after the session. Later
            // changes are the owner's, through the session edit (with a reason).
            if ($locked->report !== null && trim($locked->report) !== '') {
                throw AttendanceException::reportAlreadySaved();
            }

            if ($locked->ended_at->lt(AlgerianDate::now()->utc()->subHours(self::WINDOW_HOURS))) {
                throw AttendanceException::reportTooOld();
            }

            $locked->update([
                'report' => $report,
                'blockers' => $blockers,
            ]);

            return $locked->refresh();
        });
    }
}
