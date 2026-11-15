<?php

namespace App\Models;

use Database\Factories\AttendanceSettingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One row holds the attendance configuration. Times are Algiers wall-clock
 * times, stored as TIME without a timezone.
 *
 * @property int $id
 * @property string $auto_close_time
 * @property string $digest_time
 * @property bool $reminders_enabled
 * @property int $reminder_after_minutes
 * @property int $away_limit_minutes
 * @property int $paid_break_minutes
 * @property bool $self_correction_enabled
 * @property int $self_correction_hours
 * @property bool $over_10h_reminder_enabled
 * @property string|null $owner_digest_channel_id
 * @property string|null $reports_channel_id
 */
#[Fillable([
    'auto_close_time', 'digest_time', 'reminders_enabled', 'reminder_after_minutes',
    'away_limit_minutes', 'paid_break_minutes', 'self_correction_enabled',
    'self_correction_hours', 'over_10h_reminder_enabled', 'owner_digest_channel_id',
    'reports_channel_id',
])]
class AttendanceSetting extends Model
{
    /** @use HasFactory<AttendanceSettingFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'reminders_enabled' => 'boolean',
            'reminder_after_minutes' => 'integer',
            'away_limit_minutes' => 'integer',
            'paid_break_minutes' => 'integer',
            'self_correction_enabled' => 'boolean',
            'self_correction_hours' => 'integer',
            'over_10h_reminder_enabled' => 'boolean',
        ];
    }

    public static function current(): self
    {
        $setting = static::query()->first();

        if ($setting !== null) {
            return $setting;
        }

        return static::query()->create([])->refresh();
    }
}
