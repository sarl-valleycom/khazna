<?php

namespace App\Actions\Attendance;

use App\Actions\Attendance\Concerns\LocksEmployee;
use App\Attendance\AttendanceException;
use App\Attendance\CorrectionResult;
use App\Enums\AttendanceReviewStatus;
use App\Enums\AttendanceStatus;
use App\Enums\CorrectionStatus;
use App\Models\AttendanceSession;
use App\Models\AttendanceSetting;
use App\Models\CorrectionRequest;
use App\Models\Employee;
use App\Support\AlgerianDate;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class SubmitCorrection
{
    use LocksEmployee;

    public function handle(
        Employee $employee,
        AttendanceSession $session,
        CarbonImmutable $proposedEnd,
        ?string $note = null,
        ?CarbonImmutable $now = null,
    ): CorrectionResult {
        $now = ($now ?? AlgerianDate::now())->utc();
        $proposedEnd = $proposedEnd->utc();

        return DB::transaction(function () use ($employee, $session, $proposedEnd, $note, $now): CorrectionResult {
            $lockedEmployee = $this->lockEmployee($employee->id);

            $locked = AttendanceSession::query()
                ->whereKey($session->id)
                ->where('employee_id', $lockedEmployee->id)
                ->lockForUpdate()
                ->first();

            if ($locked === null) {
                throw AttendanceException::sessionNotFound();
            }

            if ($locked->ended_at === null) {
                throw AttendanceException::notClosed();
            }

            if ($proposedEnd->lte($locked->started_at) || $proposedEnd->gt($now)) {
                throw AttendanceException::invalidEndTime();
            }

            $settings = AttendanceSetting::current();
            $deadline = $locked->ended_at->addHours($settings->self_correction_hours);

            // Only a forgotten check-out (auto-closed, not yet reviewed) can be
            // fixed without the owner, once, within the window, and not into a
            // session long enough to need review anyway. A self-approval flips
            // review_status, so it cannot be repeated to push the end forward.
            $selfApproved = $settings->self_correction_enabled
                && $locked->status === AttendanceStatus::AutoClosed
                && $locked->review_status === AttendanceReviewStatus::NeedsReview
                && $now->lte($deadline)
                && $proposedEnd->getTimestamp() - $locked->started_at->getTimestamp() <= AttendanceSession::LONG_SESSION_SECONDS;

            $request = CorrectionRequest::query()->firstOrNew([
                'session_id' => $locked->id,
                'status' => CorrectionStatus::Pending,
            ]);

            $request->fill([
                'employee_id' => $lockedEmployee->id,
                'proposed_ended_at' => $proposedEnd,
                'note' => $note === null ? null : mb_substr(trim($note), 0, 2000),
                'status' => $selfApproved ? CorrectionStatus::Approved : CorrectionStatus::Pending,
                'decided_at' => $selfApproved ? $now : null,
            ])->save();

            if ($selfApproved) {
                $locked->update([
                    'ended_at' => $proposedEnd,
                    'review_status' => AttendanceReviewStatus::Approved,
                ]);
            }

            return new CorrectionResult($selfApproved, $request->refresh());
        });
    }
}
