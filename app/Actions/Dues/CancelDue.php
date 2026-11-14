<?php

namespace App\Actions\Dues;

use App\Enums\DueStatus;
use App\Models\Due;
use App\Support\AuditContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CancelDue
{
    public function handle(Due $due, string $reason): Due
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw ValidationException::withMessages([
                'reason' => __('Enter a reason.'),
            ]);
        }

        return DB::transaction(function () use ($due, $reason): Due {
            $due = Due::query()->lockForUpdate()->findOrFail($due->id);

            if (! $due->status->isOpen()) {
                throw ValidationException::withMessages([
                    'reason' => __('Only open dues can be canceled.'),
                ]);
            }

            if ($due->amount_paid_cents > 0) {
                throw ValidationException::withMessages([
                    'reason' => __('This due has payments on it. Reverse them first, or leave it open until it is settled.'),
                ]);
            }

            app(AuditContext::class)->withReason($reason, fn () => $due->update([
                'status' => DueStatus::Canceled,
                'canceled_reason' => $reason,
            ]));

            return $due->refresh();
        });
    }
}
