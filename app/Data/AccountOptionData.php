<?php

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class AccountOptionData extends Data
{
    public function __construct(
        public int $id,
        public int $officeId,
        public string $officeName,
        public string $name,
        public string $type,
        public int $balanceCents,
        public string $balanceFormatted,
    ) {}
}
