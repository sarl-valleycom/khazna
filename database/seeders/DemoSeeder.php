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

        $password = Hash::make((string) config('demo.password'));

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
}
