<?php

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class ClientImportRowData extends Data
{
    public function __construct(
        public int $line,
        public string $status,
        public ?string $name,
        public ?string $phoneDisplay,
        public ?string $managerEmail,
        public ?string $reason,
    ) {}
}
