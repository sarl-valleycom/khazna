<?php

namespace App\Actions\Attendance;

use App\Actions\Attendance\Concerns\LocksEmployee;
use App\Enums\AttendanceReviewStatus;
use App\Enums\CorrectionStatus;
use App\Models\AttendanceSession;
use App\Models\CorrectionRequest;
use App\Models\User;
use App\Support\AlgerianDate;
use App\Support\AuditContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Approving a correction moves the session end to the proposed time. Rejecting
 * leaves the session as it was, still waiting for review.
 */
class DecideCorrection
{
    use LocksEmployee;

    public function handle(
        CorrectionRequest $request,
        bool $approve,
        string $reason,
        User $actor,
    ): CorrectionRequest {
        return DB::transaction(function () use ($request, $approve, $reason, $actor): CorrectionRequest {
            $this->lockEmployee($request->employee_id);

            $locked = CorrectionRequest::query()
                ->whereKey($request->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status !== CorrectionStatus::Pending) {
                throw ValidationException::withMessages([
                    'status' => __('This correction request was already decided.'),
                ]);
            }

            $session = AttendanceSession::query()
                ->whereKey($locked->session_id)
                ->lockForUpdate()
                ->firstOrFail();

            $now = AlgerianDate::now()->utc();

            if ($approve && ($locked->proposed_ended_at->lte($session->started_at) || $locked->proposed_ended_at->gt($now))) {
                throw ValidationException::withMessages([
                    'proposed_ended_at' => __('The proposed end time is not valid for this session.'),
                ]);
            }

            app(AuditContext::class)->withReason($reason, function () use ($locked, $session, $approve, $actor, $now): void {
                if ($approve) {
                    $session->update([
                        'ended_at' => $locked->proposed_ended_at,
                        'review_status' => AttendanceReviewStatus::Approved,
                    ]);
                }

                $locked->update([
                    'status' => $approve ? CorrectionStatus::Approved : CorrectionStatus::Rejected,
                    'decided_by' => $actor->id,
                    'decided_at' => $now,
                ]);
            });

            return $locked->refresh();
        });
    }
}
