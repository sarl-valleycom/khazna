<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Settings for the current request, loaded with one query the first time any of
 * them is read. Bound as a scoped singleton so tests and queue workers never see
 * a stale copy.
 */
class SettingsStore
{
    /** @var array<string, string|null>|null */
    private ?array $values = null;

    public function get(string $key, ?string $default = null): ?string
    {
        $this->values ??= Setting::query()->pluck('value', 'key')->all();

        $value = $this->values[$key] ?? null;

        return $value === null ? $default : (string) $value;
    }

    public function remember(string $key, ?string $value): void
    {
        $this->values ??= Setting::query()->pluck('value', 'key')->all();
        $this->values[$key] = $value;
    }
}
