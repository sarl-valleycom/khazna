<?php

namespace Tests;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Fortify\Features;

abstract class TestCase extends BaseTestCase
{
    /**
     * Create and sign in a user with the given role.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function actingAsRole(Role $role, array $attributes = []): User
    {
        $user = User::factory()->create([
            ...$attributes,
            'role' => $role,
        ]);

        $this->actingAs($user);

        return $user;
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}
