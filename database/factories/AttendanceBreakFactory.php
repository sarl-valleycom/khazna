<?php

namespace Database\Factories;

use App\Models\AttendanceBreak;
use App\Models\AttendanceSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AttendanceBreak>
 */
class AttendanceBreakFactory extends Factory
{
    protected $model = AttendanceBreak::class;

    public function definition(): array
    {
        return [
            'session_id' => AttendanceSession::factory(),
            'started_at' => now()->subHour(),
            'ended_at' => now()->subMinutes(50),
            'reason' => 'Coffee',
            'paid' => false,
        ];
    }

    public function open(): static
    {
        return $this->state(fn (): array => ['ended_at' => null]);
    }
}
