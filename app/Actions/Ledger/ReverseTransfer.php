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

            $entries = AccountEntry::query()
                ->where('source_type', $transfer->getMorphClass())
                ->where('source_id', $transfer->id)
                ->orderBy('id')
                ->get();

            // Lock the accounts in ascending id order, the same order RecordTransfer uses, so two
            // opposite transfers being recorded or reversed can never wait on each other.
            $accounts = Account::query()
                ->whereIn('id', $entries->pluck('account_id')->unique()->all())
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($entries as $entry) {
                $this->ledger->record(
                    $accounts->get($entry->account_id) ?? throw ValidationException::withMessages(['reason' => __('An account of this transfer no longer exists.')]),
                    $entry->direction === Direction::In ? Direction::Out : Direction::In,
                    $entry->amount_cents,
                    $today,
                    $transfer,
                    $actor,
                    __('Reversal of transfer #:id', ['id' => $transfer->id]),
                    categoryId: $entry->category_id,
                    isTransfer: $entry->is_transfer,
                    isReversal: true,
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
