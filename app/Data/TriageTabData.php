<?php

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class TriageTabData extends Data
{
    /**
     * Amounts are null for a tab that lists contracts rather than dues.
     */
    public function __construct(
        public string $value,
        public string $label,
        public int $count,
        public ?int $collectCents,
        public ?int $payCents,
    ) {}
}
