<?php

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class DevUiData extends Data
{
    /**
     * @param  array<OfficeOptionData>  $offices
     * @param  array<AccountOptionData>  $accounts
     * @param  array<DemoDueRowData>  $rows
     * @param  array<ForecastPointData>  $forecast
     * @param  array<string, string>  $statuses
     */
    public function __construct(
        public string $title,
        public string $clientNameArabic,
        public int $amountCents,
        public string $amountFormatted,
        public string $date,
        public array $offices,
        public array $accounts,
        public array $rows,
        public array $forecast,
        public array $statuses,
    ) {}
}
