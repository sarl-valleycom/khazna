<?php

namespace App\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum MilestoneTrigger: string
{
    case Date = 'date';
    case Delivery = 'delivery';

    public function label(): string
    {
        return match ($this) {
            self::Date => 'On a date',
            self::Delivery => 'On delivery',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
