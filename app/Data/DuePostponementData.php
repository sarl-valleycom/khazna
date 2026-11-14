<?php

namespace App\Data;

use App\Models\DuePostponement;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class DuePostponementData extends Data
{
    public function __construct(
        public string $oldDueOn,
        public string $newDueOn,
        public string $reason,
        public string $userName,
        public ?string $at,
    ) {}

    public static function fromPostponement(DuePostponement $postponement): self
    {
        return new self(
            oldDueOn: $postponement->old_due_on->toDateString(),
            newDueOn: $postponement->new_due_on->toDateString(),
            reason: $postponement->reason,
            userName: $postponement->user->name ?? '',
            at: $postponement->created_at?->toIso8601String(),
        );
    }
}
