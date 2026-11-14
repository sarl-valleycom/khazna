<?php

namespace App\Data;

use App\Enums\AccountType;
use App\Models\Account;
use App\Models\User;
use App\Support\Money;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class AccountRowData extends Data
{
    public function __construct(
        public int $id,
        public int $officeId,
        public string $officeName,
        public string $name,
        public AccountType $type,
        public string $typeLabel,
        public string $currency,
        public int $openingBalanceCents,
        public string $openingBalanceFormatted,
        public string $openingDate,
        public int $balanceCents,
        public string $balanceFormatted,
        public bool $active,
        public bool $canEdit,
        public bool $openingLocked,
    ) {}

    /**
     * @param  bool  $openingLocked  True once the account has entries, so its opening data can no longer change.
     */
    public static function fromAccount(Account $account, User $viewer, bool $openingLocked = false): self
    {
        $balance = (int) ($account->balance_cents ?? $account->opening_balance_cents);

        return new self(
            id: $account->id,
            officeId: $account->office_id,
            officeName: $account->office->name ?? '',
            name: $account->name,
            type: $account->type,
            typeLabel: $account->type->label(),
            currency: $account->currency,
            openingBalanceCents: $account->opening_balance_cents,
            openingBalanceFormatted: Money::fromCents($account->opening_balance_cents)->format(),
            openingDate: $account->opening_date->toDateString(),
            balanceCents: $balance,
            balanceFormatted: Money::fromCents($balance)->format(),
            active: $account->active,
            canEdit: $viewer->can('update', $account),
            openingLocked: $openingLocked,
        );
    }
}
