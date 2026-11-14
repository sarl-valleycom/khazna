<?php

namespace Database\Factories;

use App\Enums\AccountType;
use App\Models\Account;
use App\Models\Office;
use App\Support\AlgerianDate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Account>
 */
class AccountFactory extends Factory
{
    protected $model = Account::class;

    public function definition(): array
    {
        return [
            'office_id' => Office::factory(),
            'name' => fake()->unique()->words(2, true),
            'type' => AccountType::Cash,
            'currency' => 'DZD',
            'opening_balance_cents' => 0,
            'opening_date' => AlgerianDate::today()->subDays(60)->toDateString(),
            'active' => true,
        ];
    }

    public function forOffice(Office $office): static
    {
        return $this->state(fn (): array => ['office_id' => $office->id]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['active' => false]);
    }
}
