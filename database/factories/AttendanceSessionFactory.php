<?php

namespace Database\Factories;

use App\Enums\AttendanceReviewStatus;
use App\Enums\AttendanceSource;
use App\Enums\AttendanceStatus;
use App\Models\AttendanceSession;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AttendanceSession>
 */
class AttendanceSessionFactory extends Factory
{
    protected $model = AttendanceSession::class;

    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'started_at' => now()->subHours(8),
            'ended_at' => now(),
            'status' => AttendanceStatus::Closed,
            'review_status' => AttendanceReviewStatus::Ok,
            'source' => AttendanceSource::Web,
        ];
    }

    public function open(): static
    {
        return $this->state(fn (): array => [
            'ended_at' => null,
            'status' => AttendanceStatus::Open,
        ]);
    }

    public function autoClosed(): static
    {
        return $this->state(fn (): array => [
            'status' => AttendanceStatus::AutoClosed,
            'review_status' => AttendanceReviewStatus::NeedsReview,
        ]);
    }
}
