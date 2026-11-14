<?php

namespace App\Data;

use App\Models\Contract;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class AttentionContractData extends Data
{
    public function __construct(
        public int $id,
        public int $clientId,
        public string $clientName,
        public string $officeName,
        public int $amountCents,
        public string $cycleLabel,
        public string $startsOn,
        public ?string $endsOn,
        public string $reason,
    ) {}

    public static function fromContract(Contract $contract): self
    {
        return new self(
            id: $contract->id,
            clientId: $contract->client_id,
            clientName: $contract->client->name ?? '',
            officeName: $contract->office->name ?? '',
            amountCents: $contract->amount_cents,
            cycleLabel: $contract->cycle->label(),
            startsOn: $contract->starts_on->toDateString(),
            endsOn: $contract->ends_on?->toDateString(),
            reason: __('Active, but no further due is scheduled. Renew it or close it.'),
        );
    }
}
