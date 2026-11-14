<?php

namespace Database\Factories;

use App\Enums\Direction;
use App\Enums\DueKind;
use App\Enums\DueStatus;
use App\Models\Client;
use App\Models\Due;
use App\Models\Office;
use App\Support\AlgerianDate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Due>
 */
class DueFactory extends Factory
{
    protected $model = Due::class;

    public function definition(): array
    {
        $dueOn = AlgerianDate::today()->addDays(7);

        return [
            'kind' => DueKind::Saas,
            'direction' => Direction::In,
            'contract_id' => null,
            'client_id' => Client::factory(),
            'office_id' => Office::factory(),
            'period' => $dueOn->toDateString(),
            'due_on' => $dueOn->toDateString(),
            'amount_due_cents' => fake()->numberBetween(1, 5000) * 1000,
            'amount_paid_cents' => 0,
            'status' => DueStatus::Pending,
            'description' => null,
            'canceled_reason' => null,
        ];
    }

    public function forClient(Client $client): static
    {
        return $this->state(fn (): array => [
            'client_id' => $client->id,
            'office_id' => $client->office_id,
        ]);
    }
}
