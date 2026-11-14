<?php

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class TriageTabData extends Data
{
    public function __construct(
        public string $value,
        public string $label,
        public int $count,
        public ?int $totalCents,
        public ?string $totalFormatted,
    ) {}
}
