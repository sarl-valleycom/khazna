<?php

namespace App\Actions\Milestones;

use App\Models\Milestone;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MarkMilestoneRequested
{
    /** Record that the client has been asked to pay for this milestone (FR-MIL-03). */
    public function handle(Milestone $milestone): Milestone
    {
        return DB::transaction(function () use ($milestone): Milestone {
            $milestone = Milestone::query()->lockForUpdate()->findOrFail($milestone->id);

            if ($milestone->requested_at !== null) {
                throw ValidationException::withMessages([
                    'milestone' => __('The client was already asked to pay for this milestone.'),
                ]);
            }

            $milestone->update(['requested_at' => now()]);

            return $milestone->refresh();
        });
    }
}
