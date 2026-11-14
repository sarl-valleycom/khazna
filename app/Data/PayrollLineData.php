<?php

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** One employee's salary for a month, before or after it is closed. */
#[TypeScript]
class PayrollLineData extends Data
{
    public function __construct(
        public int $employeeId,
        public string $employeeName,
        public string $officeName,
        public int $salaryCents,
        public int $bonusCents,
        public int $deductionCents,
        public int $advanceCents,
        public int $netCents,
        /** "ready", "exists" (already closed), "nothing" (net is zero or less) */
        public string $state,
        public ?int $dueId,
        public ?string $note,
    ) {}
}
