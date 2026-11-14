<?php

namespace App\Actions\Ledger;

use App\Enums\Direction;
use App\Models\Account;
use App\Models\Category;
use App\Models\Reconciliation;
use App\Models\User;
use App\Support\AlgerianDate;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaveReconciliation
{
    public function __construct(private readonly LedgerWriter $ledger) {}

    /**
     * "On this date the real balance was X." Khazna works out its own balance at the end
     * of that date and the difference (FR-ACC-11). A difference is either recorded as an
     * adjustment entry, with a reason, under "Reconciliation difference", or left open.
     * The reconciliation itself is history and is never edited.
     */
    public function handle(User $actor, int $accountId, string $asOf, int $countedCents, ?string $note, bool $recordDifference, ?string $reason): Reconciliation
    {
        $on = AlgerianDate::toAlgiers($asOf)->startOfDay();

        if ($on->gt(AlgerianDate::today())) {
            throw ValidationException::withMessages(['as_of' => __('The date cannot be in the future.')]);
        }

        return DB::transaction(function () use ($actor, $accountId, $on, $countedCents, $note, $recordDifference, $reason): Reconciliation {
            $account = Account::query()->visibleTo($actor)->where('active', true)->lockForUpdate()->find($accountId)
                ?? throw ValidationException::withMessages(['account_id' => __('Choose one of the accounts available to you.')]);

            if ($on->toDateString() < $account->opening_date->toDateString()) {
                throw ValidationException::withMessages([
                    'as_of' => __('This account starts on :date. Choose that date or a later one.', ['date' => $account->opening_date->format('d M Y')]),
                ]);
            }

            $system = $account->balanceOn($on);
            $difference = $countedCents - $system;

            $reconciliation = Reconciliation::query()->create([
                'account_id' => $account->id,
                'as_of' => $on->toDateString(),
                'counted_balance_cents' => $countedCents,
                'system_balance_cents' => $system,
                'difference_cents' => $difference,
                'note' => $note !== null && trim($note) !== '' ? trim($note) : null,
                'created_by' => $actor->id,
            ]);

            if ($recordDifference && $difference !== 0) {
                $this->adjust($actor, $account, $reconciliation, (string) $reason);
            }

            return $reconciliation->refresh();
        });
    }

    /**
     * Turn an open difference into an adjustment entry, dated on the reconciliation date
     * so the statement agrees with the count on that day.
     */
    public function recordOpenDifference(User $actor, Reconciliation $reconciliation, string $reason): Reconciliation
    {
        return DB::transaction(function () use ($actor, $reconciliation, $reason): Reconciliation {
            $reconciliation = Reconciliation::query()->lockForUpdate()->findOrFail($reconciliation->id);
            $account = Account::query()->visibleTo($actor)->lockForUpdate()->find($reconciliation->account_id)
                ?? throw ValidationException::withMessages(['account_id' => __('Choose one of the accounts available to you.')]);

            if ($reconciliation->adjustment_entry_id !== null || $reconciliation->difference_cents === 0) {
                throw ValidationException::withMessages(['reason' => __('There is no open difference to record.')]);
            }

            // The ledger may have moved since the count (a backdated entry, a reversal). Only
            // record the difference if the balance on that day still disagrees by the same amount.
            $now = $reconciliation->counted_balance_cents - $account->balanceOn($reconciliation->as_of);

            if ($now !== $reconciliation->difference_cents) {
                throw ValidationException::withMessages([
                    'reason' => __('The ledger changed after this count, so the difference is no longer :old. Count the account again.', [
                        'old' => Money::fromCents(abs($reconciliation->difference_cents))->format(),
                    ]),
                ]);
            }

            $this->adjust($actor, $account, $reconciliation, $reason);

            return $reconciliation->refresh();
        });
    }

    private function adjust(User $actor, Account $account, Reconciliation $reconciliation, string $reason): void
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw ValidationException::withMessages(['reason' => __('Enter a reason for the adjustment.')]);
        }

        $difference = $reconciliation->difference_cents;

        $entry = $this->ledger->record(
            account: $account,
            direction: $difference > 0 ? Direction::In : Direction::Out,
            amountCents: abs($difference),
            entryDate: $reconciliation->as_of,
            source: $reconciliation,
            by: $actor,
            description: __('Reconciliation adjustment: :reason', ['reason' => $reason]),
            dateField: 'as_of',
            categoryId: Category::named(Category::RECONCILIATION)->id,
        );

        $reconciliation->update(['adjustment_entry_id' => $entry->id]);
    }
}
