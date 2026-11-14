<?php

namespace App\Data;

use App\Models\Account;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * An account as offered in the "record a payment" form. It deliberately has no
 * balance: account managers may pick an account but must never see balances.
 */
#[TypeScript]
class PaymentAccountData extends Data
{
    public function __construct(
        public int $id,
        public int $officeId,
        public string $officeName,
        public string $name,
        public string $typeLabel,
    ) {}

    public static function fromAccount(Account $account): self
    {
        return new self(
            id: $account->id,
            officeId: $account->office_id,
            officeName: $account->office->name ?? '',
            name: $account->name,
            typeLabel: $account->type->label(),
        );
    }
}
