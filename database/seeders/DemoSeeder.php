<?php

namespace Database\Seeders;

use App\Actions\Dues\GenerateDues;
use App\Enums\AccountType;
use App\Enums\ContractStatus;
use App\Enums\ContractType;
use App\Enums\Cycle;
use App\Enums\Direction;
use App\Enums\Role;
use App\Models\Account;
use App\Models\Client;
use App\Models\Contract;
use App\Models\Office;
use App\Models\Setting;
use App\Models\User;
use App\Support\AlgerianDate;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class DemoSeeder extends Seeder
{
    /**
     * Seed the local/staging demo accounts.
     *
     * Offices are Algiers HQ and Oran Office; accounts are added by later
     * phases. Clients, contracts and generated dues exist so the CRM is usable locally.
     */
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('DemoSeeder must never run in production.');
        }

        $password = Hash::make($this->demoPassword());

        $algiers = Office::query()->firstOrCreate(
            ['name' => 'Algiers HQ'],
            ['city' => 'Algiers', 'active' => true],
        );
        $oran = Office::query()->firstOrCreate(
            ['name' => 'Oran Office'],
            ['city' => 'Oran', 'active' => true],
        );

        Setting::put(Setting::WEEK_START, (string) config('khazna.week_starts_on'));
        Setting::put(Setting::REMINDER_TEMPLATE, (string) config('khazna.reminder_template'));
        Setting::put(Setting::CURRENCY_LABEL, (string) config('khazna.currency_symbol'));
        Setting::put(Setting::SALARY_DUE_DAY, (string) config('khazna.salary_due_day'));

        $this->seedAccounts($algiers, $oran);

        $users = [
            [
                'name' => 'Yacine Benali',
                'email' => 'owner@khazna.test',
                'role' => Role::Owner,
                'office_id' => null,
            ],
            [
                'name' => 'Sara Haddad',
                'email' => 'finance@khazna.test',
                'role' => Role::Finance,
                'office_id' => $algiers->id,
            ],
            [
                'name' => 'Amine Boudjelal',
                'email' => 'algiers@khazna.test',
                'role' => Role::AccountManager,
                'office_id' => $algiers->id,
            ],
            [
                'name' => 'Nadia Cherif',
                'email' => 'oran@khazna.test',
                'role' => Role::AccountManager,
                'office_id' => $oran->id,
            ],
        ];

        foreach ($users as $attributes) {
            User::query()->updateOrCreate(
                ['email' => $attributes['email']],
                [
                    'name' => $attributes['name'],
                    'password' => $password,
                    'role' => $attributes['role'],
                    'active' => true,
                    'office_id' => $attributes['office_id'],
                    'email_verified_at' => now(),
                ],
            );
        }

        $algiersManager = User::query()->where('email', 'algiers@khazna.test')->firstOrFail();
        $oranManager = User::query()->where('email', 'oran@khazna.test')->firstOrFail();

        $clients = [
            ['name' => 'Atlas Distribution', 'contact' => 'Karim Bensalem', 'phone' => '213550100101', 'email' => 'contact@atlas.test', 'office_id' => $algiers->id, 'account_manager_id' => $algiersManager->id],
            ['name' => 'Mediterranee Services', 'contact' => 'Lina Merabet', 'phone' => '213661200202', 'email' => 'contact@mediterranee.test', 'office_id' => $algiers->id, 'account_manager_id' => $algiersManager->id],
            ['name' => 'Oran Logistique', 'contact' => 'Sofiane Kaddour', 'phone' => '213770300303', 'email' => 'contact@oran-logistique.test', 'office_id' => $oran->id, 'account_manager_id' => $oranManager->id],
        ];

        foreach ($clients as $attributes) {
            Client::query()->updateOrCreate(
                ['phone' => $attributes['phone']],
                $attributes,
            );
        }

        $contracts = [
            [
                'phone' => '213550100101',
                'amount_cents' => 4_500_000,
                'cycle' => Cycle::Monthly,
                'auto_renew' => true,
                'status' => ContractStatus::Active,
            ],
            [
                'phone' => '213661200202',
                'amount_cents' => 12_000_000,
                'cycle' => Cycle::Quarterly,
                'auto_renew' => true,
                'status' => ContractStatus::Active,
            ],
            [
                'phone' => '213770300303',
                'amount_cents' => 30_000_000,
                'cycle' => Cycle::Yearly,
                'auto_renew' => false,
                'status' => ContractStatus::Paused,
            ],
        ];

        foreach ($contracts as $attributes) {
            $client = Client::query()->where('phone', $attributes['phone'])->firstOrFail();

            Contract::query()->firstOrCreate(
                [
                    'client_id' => $client->id,
                    'type' => ContractType::SaasRenewal->value,
                ],
                [
                    'direction' => Direction::In,
                    'office_id' => $client->office_id,
                    'amount_cents' => $attributes['amount_cents'],
                    'cycle' => $attributes['cycle'],
                    'starts_on' => AlgerianDate::today()->startOfMonth()->toDateString(),
                    'ends_on' => null,
                    'status' => $attributes['status'],
                    'auto_renew' => $attributes['auto_renew'],
                ],
            );
        }

        app(GenerateDues::class)->handle();
    }

    /**
     * Demo money accounts, opened 60 days ago. Real accounts and opening
     * balances come from a physical count at go-live, never from a seeder.
     */
    private function seedAccounts(Office $algiers, Office $oran): void
    {
        $accounts = [
            [$algiers, 'Cash box', AccountType::Cash, 1_250_000],
            [$algiers, 'CCP', AccountType::Ccp, 8_450_050],
            [$algiers, 'Bank (BNA)', AccountType::Bank, 25_000_000],
            [$oran, 'Cash box', AccountType::Cash, 3_200_000],
        ];

        foreach ($accounts as [$office, $name, $type, $opening]) {
            Account::query()->firstOrCreate(
                ['office_id' => $office->id, 'name' => $name],
                [
                    'type' => $type,
                    'currency' => 'DZD',
                    'opening_balance_cents' => $opening,
                    'opening_date' => AlgerianDate::today()->subDays(60)->toDateString(),
                    'active' => true,
                ],
            );
        }
    }

    /**
     * Local and testing may use the well-known default. Anywhere else (such as
     * a public staging server) the password must be supplied explicitly, so a
     * guessable owner login is never created.
     */
    private function demoPassword(): string
    {
        $configured = config('demo.password');

        if (is_string($configured) && $configured !== '') {
            if (! app()->environment(['local', 'testing']) && $configured === 'password') {
                throw new RuntimeException('DEMO_PASSWORD must not be "password" outside local and testing.');
            }

            return $configured;
        }

        if (app()->environment(['local', 'testing'])) {
            return 'password';
        }

        throw new RuntimeException('Set DEMO_PASSWORD before running DemoSeeder outside local and testing.');
    }
}
