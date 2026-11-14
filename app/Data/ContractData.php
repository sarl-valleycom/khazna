<?php

namespace App\Data;

use App\Enums\ContractStatus;
use App\Enums\ContractType;
use App\Enums\Cycle;
use App\Enums\Direction;
use App\Models\Contract;
use App\Models\User;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class ContractData extends Data
{
    /**
     * @param  array{view: bool, update: bool, changeStatus: bool, downloadAttachment: bool}  $abilities
     */
    public function __construct(
        public int $id,
        public int $clientId,
        public string $clientName,
        public int $officeId,
        public string $officeName,
        public ContractType $type,
        public string $typeLabel,
        public Direction $direction,
        public string $directionLabel,
        public int $amountCents,
        public Cycle $cycle,
        public string $cycleLabel,
        public string $startsOn,
        public ?string $endsOn,
        public ContractStatus $status,
        public string $statusLabel,
        public bool $autoRenew,
        public ?string $notes,
        public ?string $attachmentName,
        public string $createdAt,
        public array $abilities,
    ) {}

    public static function fromContract(Contract $contract, User $viewer): self
    {
        $canView = $viewer->can('view', $contract);

        return new self(
            id: $contract->id,
            clientId: $contract->client_id,
            clientName: $contract->client->name ?? '',
            officeId: $contract->office_id,
            officeName: $contract->office->name ?? '',
            type: $contract->type,
            typeLabel: $contract->type->label(),
            direction: $contract->direction,
            directionLabel: $contract->direction->label(),
            amountCents: $contract->amount_cents,
            cycle: $contract->cycle,
            cycleLabel: $contract->cycle->label(),
            startsOn: $contract->starts_on->toDateString(),
            endsOn: $contract->ends_on?->toDateString(),
            status: $contract->status,
            statusLabel: $contract->status->label(),
            autoRenew: $contract->auto_renew,
            notes: $contract->notes,
            attachmentName: $contract->attachment_name,
            createdAt: $contract->created_at?->toIso8601String() ?? '',
            abilities: [
                'view' => $canView,
                'update' => $viewer->can('update', $contract),
                'changeStatus' => $viewer->can('changeStatus', $contract),
                'downloadAttachment' => $canView && $contract->attachment_path !== null,
            ],
        );
    }
}
