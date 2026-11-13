<?php

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class DemoDueRowData extends Data
{
    public function __construct(
        public int $id,
        public string $client,
        public string $clientArabic,
        public string $phone,
        public string $dueOn,
        public string $status,
        public string $direction,
        public int $amountCents,
        public string $amountFormatted,
    ) {}
}
