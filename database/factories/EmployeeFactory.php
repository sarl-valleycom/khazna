<?php

namespace Database\Factories;

use App\Enums\EmployeeStatus;
use App\Models\Employee;
use App\Models\EmployeeSalary;
use App\Models\Office;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Employee>
 */
class EmployeeFactory extends Factory
{
    protected $model = Employee::class;

    public function definition(): array
    {
        return [
            'office_id' => Office::factory(),
            'name' => fake()->name(),
            'phone' => null,
            'title' => fake()->jobTitle(),
            'hire_date' => '2024-01-01',
            'status' => EmployeeStatus::Active,
        ];
    }

    public function forOffice(Office $office): static
    {
        return $this->state(fn (): array => ['office_id' => $office->id]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['status' => EmployeeStatus::Inactive, 'left_on' => '2025-12-31']);
    }

    /**
     * @param  list<int>|null  $workDays  Carbon day-of-week numbers; null means Sunday to Thursday.
     */
    public function scheduled(string $start, string $end, int $expectedMinutes = 480, ?array $workDays = null): static
    {
        return $this->state(fn (): array => [
            'schedule_start' => $start,
            'schedule_end' => $end,
            'expected_minutes_per_day' => $expectedMinutes,
            'work_days' => $workDays,
        ]);
    }

    /** Give the employee a starting salary (in centimes) from a date. */
    public function earning(int $cents, string $from = '2024-01-01'): static
    {
        return $this->afterCreating(function (Employee $employee) use ($cents, $from): void {
            EmployeeSalary::query()->create([
                'employee_id' => $employee->id,
                'amount_cents' => $cents,
                'effective_from' => $from,
            ]);
        });
    }
}
