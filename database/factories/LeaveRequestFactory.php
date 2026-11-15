<?php

namespace Database\Factories;

use App\Enums\LeaveStatus;
use App\Enums\LeaveType;
use App\Models\Employee;
use App\Models\LeaveRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LeaveRequest>
 */
class LeaveRequestFactory extends Factory
{
    protected $model = LeaveRequest::class;

    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'type' => LeaveType::Paid,
            'from' => now()->addWeek()->toDateString(),
            'to' => now()->addWeek()->toDateString(),
            'reason' => fake()->sentence(),
            'status' => LeaveStatus::Pending,
            'decided_by' => null,
            'decided_at' => null,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (): array => [
            'status' => LeaveStatus::Approved,
            'decided_at' => now(),
        ]);
    }
}
