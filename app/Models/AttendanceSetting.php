<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
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
 * @property int $paid_break_minutes
 * @property bool $self_correction_enabled
 * @property int $self_correction_hours
 */
#[Fillable([
    'auto_close_time', 'paid_break_minutes', 'self_correction_enabled', 'self_correction_hours',
])]
class AttendanceSetting extends Model
{
    /** @use HasFactory<AttendanceSettingFactory> */
    use Auditable, HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'paid_break_minutes' => 'integer',
            'self_correction_enabled' => 'boolean',
            'self_correction_hours' => 'integer',
        ];
    }

    public static function current(): self
    {
        $setting = static::query()->first();

        if ($setting !== null) {
            return $setting;
        }

        // Employees fix their own forgotten check-out unless the owner turns it off.
        return static::query()->create(['self_correction_enabled' => true])->refresh();
    }
}
