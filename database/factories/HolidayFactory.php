<?php

namespace Database\Factories;

use App\Models\Holiday;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Holiday>
 */
class HolidayFactory extends Factory
{
    protected $model = Holiday::class;

    public function definition(): array
    {
        return [
            'date' => now()->addMonth()->startOfMonth()->toDateString(),
            'label' => fake()->words(2, true),
        ];
    }
}
