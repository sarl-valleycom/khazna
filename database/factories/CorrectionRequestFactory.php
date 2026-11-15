<?php

namespace Database\Factories;

use App\Enums\CorrectionStatus;
use App\Models\AttendanceSession;
use App\Models\CorrectionRequest;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CorrectionRequest>
 */
class CorrectionRequestFactory extends Factory
{
    protected $model = CorrectionRequest::class;

    public function definition(): array
    {
        return [
            'session_id' => AttendanceSession::factory()->autoClosed(),
            'employee_id' => Employee::factory(),
            'proposed_ended_at' => now()->subHour(),
            'note' => null,
            'status' => CorrectionStatus::Pending,
        ];
    }
}
