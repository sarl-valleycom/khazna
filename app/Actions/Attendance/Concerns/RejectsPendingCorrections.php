<?php

namespace App\Actions\Attendance\Concerns;

use App\Enums\CorrectionStatus;
use App\Models\AttendanceSession;
use App\Models\User;
use App\Support\AlgerianDate;

/**
 * A pending correction proposes an end time for the session as it was when the
 * employee asked. Once the owner edits or deletes that session the proposal is
 * stale, so it is closed as rejected instead of lingering in the inbox.
 */
trait RejectsPendingCorrections
{
    protected function rejectPendingCorrections(AttendanceSession $session, ?User $actor = null): void
    {
        $session->correctionRequests()
            ->pending()
            ->lockForUpdate()
            ->get()
            ->each(fn ($request) => $request->update([
                'status' => CorrectionStatus::Rejected,
                'decided_by' => $actor?->id,
                'decided_at' => AlgerianDate::now()->utc(),
            ]));
    }
}
