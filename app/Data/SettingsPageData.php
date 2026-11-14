<?php

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class SettingsPageData extends Data
{
    /**
     * @param  array<string, string>  $weekStartOptions
     */
    public function __construct(
        public string $weekStart,
        public string $reminderTemplate,
        public string $currencyLabel,
        public int $salaryDueDay,
        public array $weekStartOptions,
    ) {}
}
