<?php

namespace App\Data;

use App\Models\EmployeeSalary;
use App\Support\Money;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class SalaryHistoryData extends Data
{
    public function __construct(
        public int $id,
        public int $amountCents,
        public string $amountFormatted,
        public string $effectiveFrom,
    ) {}

    public static function fromSalary(EmployeeSalary $salary): self
    {
        return new self(
            id: $salary->id,
            amountCents: $salary->amount_cents,
            amountFormatted: Money::fromCents($salary->amount_cents)->format(),
            effectiveFrom: $salary->effective_from->toDateString(),
        );
    }
}
