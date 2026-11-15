<?php

namespace App\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum AttendanceStatus: string
{
    case Open = 'open';
    case Closed = 'closed';
    case AutoClosed = 'auto_closed';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::Closed => 'Closed',
            self::AutoClosed => 'Auto-closed',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
