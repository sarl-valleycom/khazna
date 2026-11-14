<?php

namespace App\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum ContractStatus: string
{
    case Active = 'active';
    case Paused = 'paused';
    case Canceled = 'canceled';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function canTransitionTo(self $target): bool
    {
        return match ($this) {
            self::Active => in_array($target, [self::Paused, self::Canceled], true),
            self::Paused => in_array($target, [self::Active, self::Canceled], true),
            self::Canceled => false,
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
