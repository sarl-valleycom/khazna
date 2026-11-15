<?php

namespace Database\Factories;

use App\Models\AttendanceSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AttendanceSetting>
 */
class AttendanceSettingFactory extends Factory
{
    protected $model = AttendanceSetting::class;

    public function definition(): array
    {
        return [
            'auto_close_time' => '23:59:00',
            'paid_break_minutes' => 0,
            'self_correction_enabled' => true,
            'self_correction_hours' => 24,
        ];
    }
}
