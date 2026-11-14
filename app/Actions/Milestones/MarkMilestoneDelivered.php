<?php

namespace App\Actions\Milestones;

use App\Enums\DueStatus;
use App\Enums\MilestoneTrigger;
use App\Models\Due;
use App\Models\Milestone;
use App\Models\Setting;
use App\Support\AlgerianDate;
use App\Support\AuditContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MarkMilestoneDelivered
{
    /**
     * The work was delivered. A delivery-triggered milestone becomes due after the
     * payment term (a setting, 0 days by default); a dated one keeps its date (FR-MIL-02).
     */
    public function handle(Milestone $milestone): Milestone
    {
        return DB::transaction(function () use ($milestone): Milestone {
            $milestone = Milestone::query()->lockForUpdate()->findOrFail($milestone->id);
            $due = Due::query()->lockForUpdate()->where('milestone_id', $milestone->id)->firstOrFail();

            if ($milestone->delivered_at !== null) {
                throw ValidationException::withMessages([
                    'milestone' => __('This milestone is already marked as delivered.'),
                ]);
            }

            // A milestone the client paid in advance can still be delivered; only a canceled one cannot.
            if ($due->status === DueStatus::Canceled) {
                throw ValidationException::withMessages([
                    'milestone' => __('This milestone was canceled.'),
                ]);
            }

            $delivered = AlgerianDate::today();
            $term = max(0, (int) Setting::current(Setting::MILESTONE_PAYMENT_TERM_DAYS, '0'));

            app(AuditContext::class)->withReason(__('Milestone delivered'), function () use ($milestone, $due, $delivered, $term): void {
                $milestone->update(['delivered_at' => $delivered->toDateString()]);

                if ($milestone->trigger === MilestoneTrigger::Delivery) {
                    $dueOn = $delivered->addDays($term)->toDateString();
                    $milestone->update(['due_on' => $dueOn]);

                    // An already-settled due keeps no date to chase: only an open one is dated.
                    if ($due->status->isOpen()) {
                        $due->update(['due_on' => $dueOn]);
                    }
                }
            });

            return $milestone->refresh();
        });
    }
}
