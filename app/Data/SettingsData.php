<?php

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class SettingsData extends Data
{
    public function __construct(
        public string $appName,
        public string $locale,
        public string $displayTimezone,
        public string $weekStartsOn,
        public string $currency,
        public string $currencySymbol,
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            appName: (string) config('app.name'),
            locale: (string) config('khazna.locale'),
            displayTimezone: (string) config('khazna.display_timezone'),
            weekStartsOn: (string) config('khazna.week_starts_on'),
            currency: (string) config('khazna.currency'),
            currencySymbol: (string) config('khazna.currency_symbol'),
        );
    }
}
