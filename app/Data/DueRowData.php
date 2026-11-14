<?php

namespace App\Data;

use App\Enums\Direction;
use App\Enums\DueKind;
use App\Enums\DueStatus;
use App\Models\Due;
use App\Models\DuePostponement;
use App\Models\User;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class DueRowData extends Data
{
    /**
     * @param  array{postpone: bool, cancel: bool, pay: bool, remind: bool}  $abilities
     */
    public function __construct(
        public int $id,
        public ?int $contractId,
        public int $clientId,
        public string $clientName,
        public int $officeId,
        public string $officeName,
        public ?string $description,
        public DueKind $kind,
        public string $kindLabel,
        public Direction $direction,
        public string $directionLabel,
        public string $dueOn,
        public int $amountDueCents,
        public int $amountPaidCents,
        public int $remainingCents,
        public int $creditCents,
        public DueStatus $status,
        public string $statusLabel,
        public bool $isOverdue,
        public ?string $canceledReason,
        /** @var array<int, DuePostponementData> */
        public array $postponements,
        public array $abilities,
    ) {}

    public static function fromDue(Due $due, User $viewer): self
    {
        return new self(
            id: $due->id,
            contractId: $due->contract_id,
            clientId: $due->client_id,
            clientName: $due->client->name ?? '',
            officeId: $due->office_id,
            officeName: $due->office->name ?? '',
            description: $due->description,
            kind: $due->kind,
            kindLabel: $due->kind->label(),
            direction: $due->direction,
            directionLabel: $due->direction->label(),
            dueOn: $due->due_on->toDateString(),
            amountDueCents: $due->amount_due_cents,
            amountPaidCents: $due->amount_paid_cents,
            remainingCents: $due->remainingCents(),
            creditCents: $due->creditCents(),
            status: $due->status,
            statusLabel: $due->isOverdue() ? 'Overdue' : $due->status->label(),
            isOverdue: $due->isOverdue(),
            canceledReason: $due->canceled_reason,
            // Only when the caller eager-loaded them, so a list never lazy-loads per row.
            postponements: $due->relationLoaded('postponements')
                ? $due->postponements->map(fn (DuePostponement $postponement): DuePostponementData => DuePostponementData::fromPostponement($postponement))->values()->all()
                : [],
            abilities: [
                'postpone' => $viewer->can('postpone', $due),
                'cancel' => $viewer->can('cancel', $due),
                'pay' => $viewer->can('pay', $due),
                'remind' => $viewer->can('remind', $due),
            ],
        );
    }
}
