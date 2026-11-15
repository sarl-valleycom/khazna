<?php

namespace App\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum EmploymentType: string
{
    case FixedMonthly = 'fixed_monthly';
    case Hourly = 'hourly';
    case PartTime = 'part_time';
    case Freelancer = 'freelancer';

    public function label(): string
    {
        return match ($this) {
            self::FixedMonthly => 'Fixed monthly',
            self::Hourly => 'Hourly',
            self::PartTime => 'Part-time',
            self::Freelancer => 'Freelancer',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
