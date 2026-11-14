<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\Office;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Client>
 */
class ClientFactory extends Factory
{
    protected $model = Client::class;

    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'contact' => fake()->name(),
            'phone' => '0'.fake()->unique()->numerify('5########'),
            'email' => fake()->unique()->companyEmail(),
            'address' => fake()->address(),
            'notes' => null,
            'account_manager_id' => User::factory()->accountManager(),
            'office_id' => null,
            'crm_lead_id' => null,
        ];
    }

    public function forManager(User $manager): static
    {
        return $this->state(fn (): array => ['account_manager_id' => $manager->id]);
    }

    public function forOffice(Office $office): static
    {
        return $this->state(fn (): array => ['office_id' => $office->id]);
    }
}
