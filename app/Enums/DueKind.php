<?php

namespace App\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum DueKind: string
{
    case Saas = 'saas';
    case Project = 'project';
    case Salary = 'salary';
    case Expense = 'expense';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Saas => 'SaaS',
            self::Project => 'Project',
            self::Salary => 'Salary',
            self::Expense => 'Expense',
            self::Other => 'Other',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
