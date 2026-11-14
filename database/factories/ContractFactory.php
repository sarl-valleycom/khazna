<?php

namespace Database\Factories;

use App\Enums\ContractStatus;
use App\Enums\ContractType;
use App\Enums\Cycle;
use App\Enums\Direction;
use App\Models\Client;
use App\Models\Contract;
use App\Models\Office;
use App\Support\AlgerianDate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Contract>
 */
class ContractFactory extends Factory
{
    protected $model = Contract::class;

    public function definition(): array
    {
        return [
            'type' => ContractType::SaasRenewal,
            'direction' => Direction::In,
            'client_id' => Client::factory(),
            'office_id' => Office::factory(),
            'amount_cents' => fake()->numberBetween(1, 5000) * 1000,
            'cycle' => fake()->randomElement([Cycle::Monthly, Cycle::Quarterly, Cycle::Yearly]),
            'starts_on' => AlgerianDate::today()->subMonths(2)->toDateString(),
            'ends_on' => null,
            'status' => ContractStatus::Active,
            'auto_renew' => false,
            'notes' => null,
            'attachment_path' => null,
            'attachment_name' => null,
            'attachment_mime' => null,
        ];
    }
}
