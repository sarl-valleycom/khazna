<?php

namespace App\Data;

use App\Enums\Direction;
use App\Enums\DueKind;
use App\Http\Requests\Dues\DueRequest;

final readonly class DueInput
{
    public function __construct(
        public Direction $direction,
        public DueKind $kind,
        public string $dueOn,
        public int $amountCents,
        public ?string $description,
    ) {}

    public static function fromRequest(DueRequest $request): self
    {
        $validated = $request->validated();

        return new self(
            direction: Direction::from((string) $validated['direction']),
            kind: DueKind::from((string) $validated['kind']),
            dueOn: (string) $validated['due_on'],
            amountCents: (int) $validated['amount_cents'],
            description: isset($validated['description']) ? (string) $validated['description'] : null,
        );
    }
}
