<?php

namespace App\Actions\Dues;

use App\Models\Due;
use App\Models\User;
use App\Support\AlgerianDate;
use App\Support\AuditContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PostponeDue
{
    public function handle(Due $due, string $newDueOn, string $reason, User $user): Due
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw ValidationException::withMessages([
                'reason' => __('Enter a reason.'),
            ]);
        }
        $newDate = AlgerianDate::toAlgiers($newDueOn)->startOfDay();

        return DB::transaction(function () use ($due, $newDate, $reason, $user): Due {
            $due = Due::query()->lockForUpdate()->findOrFail($due->id);

            if (! $due->status->isOpen()) {
                throw ValidationException::withMessages([
                    'new_due_on' => __('Only open dues can be postponed.'),
                ]);
            }

            if ($newDate->lt(AlgerianDate::today())) {
                throw ValidationException::withMessages([
                    'new_due_on' => __('Choose today or a later date.'),
                ]);
            }

            if ($newDate->toDateString() === $due->due_on->toDateString()) {
                throw ValidationException::withMessages([
                    'new_due_on' => __('Choose a different date.'),
                ]);
            }

            $oldDueOn = $due->due_on->toDateString();

            app(AuditContext::class)->withReason($reason, fn () => $due->update([
                'due_on' => $newDate->toDateString(),
            ]));

            $due->postponements()->create([
                'old_due_on' => $oldDueOn,
                'new_due_on' => $newDate->toDateString(),
                'reason' => $reason,
                'user_id' => $user->id,
            ]);

            return $due->refresh();
        });
    }
}
