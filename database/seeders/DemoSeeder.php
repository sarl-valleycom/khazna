<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class DemoSeeder extends Seeder
{
    /**
     * Seed the local/staging demo accounts.
     *
     * Offices are represented by the ids 1 (Algiers) and 2 (Oran) until the
     * offices table exists in phase 1.
     */
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('DemoSeeder must never run in production.');
        }

        $password = Hash::make($this->demoPassword());

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
                'office_id' => 1,
            ],
            [
                'name' => 'Amine Boudjelal',
                'email' => 'algiers@khazna.test',
                'role' => Role::AccountManager,
                'office_id' => 1,
            ],
            [
                'name' => 'Nadia Cherif',
                'email' => 'oran@khazna.test',
                'role' => Role::AccountManager,
                'office_id' => 2,
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
