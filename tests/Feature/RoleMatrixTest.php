<?php

use App\Enums\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

dataset('roles', [
    'owner' => [Role::Owner],
    'finance' => [Role::Finance],
    'account manager' => [Role::AccountManager],
]);

/*
 * Add a row here for every new route:
 * ['method' => 'GET', 'uri' => '/clients', 'allowed' => [Role::Owner, Role::Finance, ...]]
 * The test below is deliberately small; it is the seed for the full matrix.
 */
test('the home screen is reachable by every active role', function (Role $role) {
    $this->actingAsRole($role);

    $this->get(route('home'))->assertOk();
})->with('roles');

test('guests cannot open the home screen', function () {
    $this->get(route('home'))->assertRedirect(route('login'));
});
