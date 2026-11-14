<?php

namespace App\Data;

use App\Enums\DueStatus;
use App\Enums\MilestoneTrigger;
use App\Models\Milestone;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class MilestoneData extends Data
{
    public function __construct(
        public int $id,
        public int $position,
        public string $label,
        public int $amountCents,
        public int $paidCents,
        public ?string $dueOn,
        public MilestoneTrigger $trigger,
        public ?string $deliveredAt,
        public ?string $requestedAt,
        public ?DueStatus $dueStatus,
        public bool $waitingForDelivery,
        public bool $locked,
        public bool $canDeliver,
        public bool $canRequest,
    ) {}

    /** Needs the milestone's due loaded. */
    public static function fromMilestone(Milestone $milestone, bool $mayManage): self
    {
        $due = $milestone->due;
        $open = $due !== null && $due->status->isOpen();

        return new self(
            id: $milestone->id,
            position: $milestone->position,
            label: $milestone->label,
            amountCents: $milestone->amount_cents,
            paidCents: $due->amount_paid_cents ?? 0,
            dueOn: $milestone->due_on?->toDateString(),
            trigger: $milestone->trigger,
            deliveredAt: $milestone->delivered_at?->toDateString(),
            requestedAt: $milestone->requested_at?->toIso8601String(),
            dueStatus: $due?->status,
            waitingForDelivery: $milestone->trigger === MilestoneTrigger::Delivery && $milestone->delivered_at === null,
            locked: ($due->amount_paid_cents ?? 0) > 0,
            canDeliver: $mayManage && $open && $milestone->delivered_at === null,
            canRequest: $mayManage && $open && $milestone->requested_at === null,
        );
    }
}
