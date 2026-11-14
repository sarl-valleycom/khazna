<?php

namespace App\Actions\Payments;

use App\Models\Account;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaveAccount
{
    /**
     * @param  array<string, mixed>  $data  Validated AccountRequest data.
     */
    public function create(array $data): Account
    {
        return Account::query()->create([
            ...$data,
            'currency' => 'DZD',
        ]);
    }

    /**
     * Once an account has entries its opening balance and date are history and
     * can no longer change. A correction is an adjustment entry (phase 2).
     *
     * @param  array<string, mixed>  $data  Validated AccountRequest data.
     */
    public function update(Account $account, array $data): Account
    {
        return DB::transaction(function () use ($account, $data): Account {
            // RecordPayment locks this same row before writing the first entry, so the
            // "has entries" check below cannot race with it.
            $account = Account::query()->lockForUpdate()->findOrFail($account->id);

            $changesOpening = (int) $data['opening_balance_cents'] !== $account->opening_balance_cents
                || $data['opening_date'] !== $account->opening_date->toDateString();

            if ($changesOpening && $account->hasEntries()) {
                throw ValidationException::withMessages([
                    'opening_balance_cents' => __('This account already has entries, so its opening balance and date can no longer change.'),
                ]);
            }

            // An account that still holds money (or owes it) cannot be switched off (FR-ACC-12).
            if (($data['active'] ?? true) === false && $account->active && $account->balanceCents() !== 0) {
                throw ValidationException::withMessages([
                    'active' => __('This account still has a balance. Move it elsewhere before deactivating the account.'),
                ]);
            }

            $account->update($data);

            return $account->refresh();
        });
    }
}
