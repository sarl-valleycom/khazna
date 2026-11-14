<?php

namespace App\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum CategoryKind: string
{
    case Cost = 'cost';
    case Income = 'income';

    public function label(): string
    {
        return match ($this) {
            self::Cost => 'Cost',
            self::Income => 'Income',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
