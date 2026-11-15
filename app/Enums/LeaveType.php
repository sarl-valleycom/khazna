<?php

namespace App\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum LeaveType: string
{
    case Paid = 'paid';
    case Unpaid = 'unpaid';
    case Sick = 'sick';

    public function label(): string
    {
        return match ($this) {
            self::Paid => 'Paid leave',
            self::Unpaid => 'Unpaid leave',
            self::Sick => 'Sick leave',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
