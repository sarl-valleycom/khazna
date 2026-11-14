<?php

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('an inactive user cannot sign in', function () {
    $user = User::factory()->inactive()->create();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('a deactivated user with the right password is told why', function () {
    $user = User::factory()->inactive()->create();

    $this->from(route('login'))->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertSessionHasErrors(['email' => __('khazna.errors.inactive_user')]);

    $this->assertGuest();
});

test('a wrong password for a deactivated user gives the generic error', function () {
    $user = User::factory()->inactive()->create();

    $this->from(route('login'))->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'not-the-password',
    ])->assertSessionHasErrors('email')
        ->assertSessionDoesntHaveErrors(['email' => __('khazna.errors.inactive_user')]);
});

test('attempts against a deactivated account count toward the login throttle', function () {
    $user = User::factory()->inactive()->create();

    foreach (range(1, 5) as $attempt) {
        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertSessionHasErrors('email');
    }

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertStatus(429);
});

test('a signed-in user who is deactivated is logged out on the next request', function () {
    $user = User::factory()->inactive()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route('login'));

    $this->assertGuest();
});

test('an active user stays signed in', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk();

    $this->assertAuthenticatedAs($user);
});

test('only the owner can view salaries by default', function () {
    $owner = User::factory()->owner()->create();
    $finance = User::factory()->finance()->create();
    $accountManager = User::factory()->accountManager()->create();

    expect($owner->can('view-salaries'))->toBeTrue()
        ->and($finance->can('view-salaries'))->toBeFalse()
        ->and($accountManager->can('view-salaries'))->toBeFalse();
});

test('the owner can grant salary visibility to finance later', function () {
    config()->set('khazna.finance_can_view_salaries', true);

    $finance = User::factory()->finance()->create();

    expect($finance->can('view-salaries'))->toBeTrue();
});

test('the role enum and office restriction are cast on the user model', function () {
    $user = User::factory()->finance()->forOffice(2)->create();

    expect($user->role)->toBe(Role::Finance)
        ->and($user->isFinance())->toBeTrue()
        ->and($user->isRestrictedToOffice())->toBeTrue()
        ->and($user->office_id)->toBe(2);
});
