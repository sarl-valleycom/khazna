<?php

use App\Enums\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->app->detectEnvironment(fn (): string => 'local');
});

test('the UI kit page renders all shared components for an authenticated user', function () {
    $this->actingAsRole(Role::Owner);

    $this->get(route('dev.ui'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dev/Ui')
            ->has('page.rows', 8)
            ->where('page.total', 8)
            ->where('page.statuses.paid', 'Paid')
            ->has('page.offices', 2)
            ->has('page.accounts', 3)
        );
});

test('the UI kit demo filters and sorts through the URL', function () {
    $this->actingAsRole(Role::Owner);

    $this->get(route('dev.ui', [
        'status' => 'paid',
        'sort' => 'amountCents',
        'direction' => 'desc',
    ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('page.total', 3)
            ->where('page.statusFilter', 'paid')
            ->where('page.sort', 'amountCents')
            ->where('page.direction', 'desc')
            ->where('page.rows.0.status', 'paid')
            ->where('page.rows.0.client', 'SARL Horizon')
        );
});

test('the UI kit demo searches by client name', function () {
    $this->actingAsRole(Role::Owner);

    $this->get(route('dev.ui', ['q' => 'Oran']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('page.total', 1)
            ->where('page.rows.0.client', 'Oran Digital')
        );
});

test('the UI kit route is hidden outside local and staging', function () {
    $this->app->detectEnvironment(fn (): string => 'production');
    $this->actingAsRole(Role::Owner);

    $this->get(route('dev.ui'))->assertNotFound();
});

test('the UI kit route is owner-only', function (Role $role) {
    $this->actingAsRole($role);

    $this->get(route('dev.ui'))->assertForbidden();
})->with([
    'finance' => [Role::Finance],
    'account manager' => [Role::AccountManager],
]);

test('the UI kit route requires a verified email', function () {
    $this->actingAsRole(Role::Owner, ['email_verified_at' => null]);

    $this->get(route('dev.ui'))->assertRedirect(route('verification.notice'));
});

test('the UI kit route looks nonexistent in production to everyone', function (?Role $role) {
    $this->app->detectEnvironment(fn (): string => 'production');

    if ($role !== null) {
        $this->actingAsRole($role);
    }

    $this->get(route('dev.ui'))->assertNotFound();
})->with([
    'guest' => [null],
    'finance' => [Role::Finance],
    'owner' => [Role::Owner],
]);
