<?php

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class ForecastPointData extends Data
{
    public function __construct(
        public string $label,
        public int $inflow,
        public int $outflow,
        public int $balance,
    ) {}
}
