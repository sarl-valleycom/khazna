<?php

namespace App\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum ContractType: string
{
    case SaasRenewal = 'saas_renewal';
    case CustomProject = 'custom_project';
    case RecurringCost = 'recurring_cost';

    public function label(): string
    {
        return match ($this) {
            self::SaasRenewal => 'SaaS renewal',
            self::CustomProject => 'Custom project',
            self::RecurringCost => 'Recurring cost',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
