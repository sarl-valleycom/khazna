<?php

namespace App\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum AdjustmentType: string
{
    case Advance = 'advance';
    case Bonus = 'bonus';
    case Deduction = 'deduction';

    public function label(): string
    {
        return match ($this) {
            self::Advance => 'Advance',
            self::Bonus => 'Bonus',
            self::Deduction => 'Deduction',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
