<?php

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class ClientImportPreviewData extends Data
{
    /**
     * @param  list<ClientImportRowData>  $rows
     */
    public function __construct(
        public string $token,
        public string $fileName,
        public int $totalCount,
        public int $validCount,
        public int $invalidCount,
        public int $duplicateCount,
        public array $rows,
    ) {}
}
