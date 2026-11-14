<?php

namespace App\Actions\Ledger;

use App\Enums\Direction;
use App\Models\Account;
use App\Models\Category;
use App\Models\Transfer;
use App\Models\User;
use App\Support\AlgerianDate;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecordTransfer
{
    public function __construct(private readonly LedgerWriter $ledger) {}

    /**
     * Move money between two accounts: an out entry on the source, an in entry on the
     * destination and, when there is a fee, a second out entry on the source filed
     * under Bank fees (FR-ACC-07). The fee is paid by the source (open question 5).
     * The two legs are marked as transfers so no report counts them as income or
     * expense; only the fee is an expense (BR-17).
     */
    public function handle(User $actor, int $fromId, int $toId, int $amountCents, int $feeCents, string $date, ?string $note): Transfer
    {
        if ($fromId === $toId) {
            throw ValidationException::withMessages([
                'to_account_id' => __('Choose two different accounts.'),
            ]);
        }

        $on = AlgerianDate::toAlgiers($date)->startOfDay();

        if ($on->gt(AlgerianDate::today())) {
            throw ValidationException::withMessages(['transfer_date' => __('The transfer date cannot be in the future.')]);
        }

        return DB::transaction(function () use ($actor, $fromId, $toId, $amountCents, $feeCents, $on, $note): Transfer {
            // Lock in a fixed order so two opposite transfers can never deadlock.
            $accounts = Account::query()
                ->visibleTo($actor)
                ->where('active', true)
                ->whereIn('id', [$fromId, $toId])
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $from = $accounts->get($fromId) ?? throw ValidationException::withMessages([
                'from_account_id' => __('Choose one of the accounts available to you.'),
            ]);
            $to = $accounts->get($toId) ?? throw ValidationException::withMessages([
                'to_account_id' => __('Choose one of the accounts available to you.'),
            ]);

            $transfer = Transfer::query()->create([
                'from_account_id' => $from->id,
                'to_account_id' => $to->id,
                'amount_cents' => $amountCents,
                'fee_cents' => $feeCents,
                'transfer_date' => $on->toDateString(),
                'note' => $note !== null && trim($note) !== '' ? trim($note) : null,
                'created_by' => $actor->id,
            ]);

            $this->ledger->record($from, Direction::Out, $amountCents, $on, $transfer, $actor, __('Transfer to :account', ['account' => $to->name]), 'transfer_date', isTransfer: true);
            $this->ledger->record($to, Direction::In, $amountCents, $on, $transfer, $actor, __('Transfer from :account', ['account' => $from->name]), 'transfer_date', isTransfer: true);

            if ($feeCents > 0) {
                $this->ledger->record($from, Direction::Out, $feeCents, $on, $transfer, $actor, __('Transfer fee'), 'transfer_date', categoryId: Category::named(Category::BANK_FEES)->id);
            }

            return $transfer;
        });
    }
}
