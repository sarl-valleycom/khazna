<?php

namespace App\Data;

use App\Enums\Direction;
use App\Models\AccountEntry;
use App\Support\Money;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class AccountStatementRowData extends Data
{
    public function __construct(
        public int $id,
        public string $entryDate,
        public string $description,
        public Direction $direction,
        public int $inCents,
        public string $inFormatted,
        public int $outCents,
        public string $outFormatted,
        public int $runningCents,
        public string $runningFormatted,
    ) {}

    public static function fromEntry(AccountEntry $entry): self
    {
        $in = $entry->direction === Direction::In ? $entry->amount_cents : 0;
        $out = $entry->direction === Direction::Out ? $entry->amount_cents : 0;
        $running = (int) $entry->running_cents;

        return new self(
            id: $entry->id,
            entryDate: $entry->entry_date->toDateString(),
            description: $entry->description ?? '',
            direction: $entry->direction,
            inCents: $in,
            inFormatted: $in > 0 ? Money::fromCents($in)->formatWithoutCurrency() : '',
            outCents: $out,
            outFormatted: $out > 0 ? Money::fromCents($out)->formatWithoutCurrency() : '',
            runningCents: $running,
            runningFormatted: Money::fromCents($running)->formatWithoutCurrency(),
        );
    }
}
