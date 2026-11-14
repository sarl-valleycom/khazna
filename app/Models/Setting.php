<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Support\SettingsStore;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $key
 * @property string|null $value
 */
#[Fillable(['key', 'value'])]
class Setting extends Model
{
    use Auditable;

    public const WEEK_START = 'week_start';

    public const REMINDER_TEMPLATE = 'reminder_template';

    public const CURRENCY_LABEL = 'currency_label';

    public const SALARY_DUE_DAY = 'salary_due_day';

    public const MILESTONE_PAYMENT_TERM_DAYS = 'milestone_payment_term_days';

    public static function current(string $key, ?string $default = null): ?string
    {
        return app(SettingsStore::class)->get($key, $default);
    }

    public static function put(string $key, ?string $value): self
    {
        $setting = static::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $value],
        );

        app(SettingsStore::class)->remember($key, $value);

        return $setting;
    }
}
