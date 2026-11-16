<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\Office;
use App\Models\User;
use Illuminate\Database\Seeder;

class ProductionSeeder extends Seeder
{
    /**
     * Seed the real offices and the first admin (owner) account.
     *
     * Idempotent: offices and the admin are matched by name / email, and an
     * existing admin keeps its current password, so re-running never resets it.
     */
    public function run(): void
    {
        foreach (['Alger', 'Ghardaia'] as $name) {
            Office::query()->firstOrCreate(
                ['name' => $name],
                ['city' => $name, 'active' => true],
            );
        }

        $admin = User::query()->firstOrNew(['email' => 'yacine@saascom.shop']);

        if (! $admin->exists) {
            $admin->password = '$82IXDNpklO';
        }

        $admin->forceFill([
            'name' => 'Yacine',
            'role' => Role::Owner,
            'active' => true,
            'office_id' => null,
            'email_verified_at' => $admin->email_verified_at ?? now(),
        ])->save();
    }
}
