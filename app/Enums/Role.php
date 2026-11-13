<?php

namespace App\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum Role: string
{
    case Owner = 'owner';
    case Finance = 'finance';
    case AccountManager = 'account_manager';

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Owner',
            self::Finance => 'Finance',
            self::AccountManager => 'Account manager',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
