<?php

namespace App\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum AttendanceSource: string
{
    case Discord = 'discord';
    case Web = 'web';
    case Admin = 'admin';

    public function label(): string
    {
        return match ($this) {
            self::Discord => 'Discord (old)',
            self::Web => 'Web',
            self::Admin => 'Admin',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
