<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Models\Account;
use App\Models\Due;
use App\Models\Payment;
use App\Support\AlgerianDate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Raw rows only. Tests that need the ledger entry and the recomputed due must
 * go through the RecordPayment action instead.
 *
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return [
            'due_id' => Due::factory(),
            'account_id' => Account::factory(),
            'amount_cents' => 100_000,
            'paid_on' => AlgerianDate::today()->toDateString(),
            'method' => PaymentMethod::Cash,
        ];
    }
}
