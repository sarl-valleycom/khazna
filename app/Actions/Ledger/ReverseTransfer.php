<?php

namespace App\Actions\Ledger;

use App\Enums\Direction;
use App\Models\Account;
use App\Models\AccountEntry;
use App\Models\Transfer;
use App\Models\User;
use App\Support\AlgerianDate;
use App\Support\AuditContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReverseTransfer
{
    public function __construct(private readonly LedgerWriter $ledger) {}

    /**
     * Undo a transfer by adding an opposite entry for every entry it wrote, fee
     * included. The originals stay (FR-ACC-04).
     */
    public function handle(User $actor, Transfer $transfer, string $reason): Transfer
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw ValidationException::withMessages(['reason' => __('Enter a reason.')]);
        }

        return DB::transaction(function () use ($actor, $transfer, $reason): Transfer {
            $transfer = Transfer::query()->lockForUpdate()->findOrFail($transfer->id);

            if ($transfer->isReversed()) {
                throw ValidationException::withMessages(['reason' => __('This transfer has already been reversed.')]);
            }

            $today = AlgerianDate::today();

            foreach (AccountEntry::query()->where('source_type', $transfer->getMorphClass())->where('source_id', $transfer->id)->orderBy('id')->get() as $entry) {
                $this->ledger->record(
                    Account::query()->lockForUpdate()->findOrFail($entry->account_id),
                    $entry->direction === Direction::In ? Direction::Out : Direction::In,
                    $entry->amount_cents,
                    $today,
                    $transfer,
                    $actor,
                    __('Reversal of transfer #:id', ['id' => $transfer->id]),
                    categoryId: $entry->category_id,
                    isTransfer: $entry->is_transfer,
                );
            }

            app(AuditContext::class)->withReason($reason, fn () => $transfer->update([
                'reversed_at' => now(),
                'reversal_reason' => $reason,
                'reversed_by' => $actor->id,
            ]));

            return $transfer->refresh();
        });
    }
}
