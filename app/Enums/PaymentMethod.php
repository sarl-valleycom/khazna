<?php

namespace App\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum PaymentMethod: string
{
    case Cash = 'cash';
    case Ccp = 'ccp';
    case Baridimob = 'baridimob';
    case BankTransfer = 'bank_transfer';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Cash',
            self::Ccp => 'CCP',
            self::Baridimob => 'BaridiMob',
            self::BankTransfer => 'Bank transfer',
            self::Other => 'Other',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
