<?php

namespace App\Actions\Ledger;

use App\Enums\Direction;
use App\Models\Account;
use App\Models\ManualEntry;
use App\Models\User;
use App\Support\AlgerianDate;
use App\Support\AuditContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReverseManualEntry
{
    public function __construct(private readonly LedgerWriter $ledger) {}

    public function handle(User $actor, ManualEntry $entry, string $reason): ManualEntry
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw ValidationException::withMessages(['reason' => __('Enter a reason.')]);
        }

        return DB::transaction(function () use ($actor, $entry, $reason): ManualEntry {
            $entry = ManualEntry::query()->lockForUpdate()->findOrFail($entry->id);

            if ($entry->isReversed()) {
                throw ValidationException::withMessages(['reason' => __('This entry has already been reversed.')]);
            }

            $this->ledger->record(
                Account::query()->lockForUpdate()->findOrFail($entry->account_id),
                $entry->direction === Direction::In ? Direction::Out : Direction::In,
                $entry->amount_cents,
                AlgerianDate::today(),
                $entry,
                $actor,
                __('Reversal: :description', ['description' => $entry->description]),
                categoryId: $entry->category_id,
                isReversal: true,
            );

            app(AuditContext::class)->withReason($reason, fn () => $entry->update([
                'reversed_at' => now(),
                'reversal_reason' => $reason,
                'reversed_by' => $actor->id,
            ]));

            return $entry->refresh();
        });
    }
}
