<?php

use App\Enums\Role;
use App\Models\Activity;
use App\Models\Client;
use App\Models\Office;
use App\Models\Setting;
use App\Models\User;
use App\Support\AlgerianDate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('guests are redirected from the admin screens', function () {
    $this->get(route('offices.index'))->assertRedirect(route('login'));
    $this->get(route('users.index'))->assertRedirect(route('login'));
    $this->get(route('settings.edit'))->assertRedirect(route('login'));
    $this->get(route('audit-log.index'))->assertRedirect(route('login'));
});

test('only the owner may open offices, users, settings and audit log', function (Role $role) {
    $this->actingAsRole($role);

    $this->get(route('offices.index'))->assertForbidden();
    $this->get(route('users.index'))->assertForbidden();
    $this->get(route('settings.edit'))->assertForbidden();
    $this->get(route('audit-log.index'))->assertForbidden();
})->with([
    'finance' => [Role::Finance],
    'account manager' => [Role::AccountManager],
]);

test('the owner sees offices with their user counts', function () {
    $this->actingAsRole(Role::Owner);
    $office = Office::factory()->create();
    User::factory()->forOffice($office->id)->create();

    $this->get(route('offices.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/Offices')
            ->has('offices', 1)
            ->where('offices.0.id', $office->id)
            ->where('offices.0.usersCount', 1));
});

test('the owner can create and update an office', function () {
    $this->actingAsRole(Role::Owner);

    $this->post(route('offices.store'), [
        'name' => 'Constantine Office',
        'city' => 'Constantine',
        'active' => true,
    ])->assertRedirect(route('offices.index'))->assertStatus(303);

    $office = Office::query()->where('name', 'Constantine Office')->firstOrFail();

    $this->patch(route('offices.update', $office), [
        'name' => 'Constantine Annex',
        'city' => 'Constantine',
        'active' => true,
    ])->assertRedirect(route('offices.index'))->assertStatus(303);

    expect($office->fresh()->name)->toBe('Constantine Annex');
});

test('the last active office cannot be deactivated', function () {
    $this->actingAsRole(Role::Owner);
    $office = Office::factory()->create(['active' => true]);

    $this->patch(route('offices.update', $office), [
        'name' => $office->name,
        'city' => $office->city,
        'active' => false,
    ])->assertSessionHasErrors('active');

    expect($office->fresh()->active)->toBeTrue();
});

test('the user screen lists roles and offices for the owner', function () {
    $owner = $this->actingAsRole(Role::Owner);
    Office::factory()->create();

    $this->get(route('users.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/Users')
            ->has('users', 1)
            ->has('offices', 1)
            ->has('roles', 3)
            ->where('users.0.id', $owner->id));
});

test('the owner can create a user with an office and a hashed password', function () {
    $this->actingAsRole(Role::Owner);
    $office = Office::factory()->create();

    $this->post(route('users.store'), [
        'name' => 'New Manager',
        'email' => 'manager@example.test',
        'role' => Role::AccountManager->value,
        'office_id' => $office->id,
        'active' => true,
        'password' => 'correct-horse-battery',
        'password_confirmation' => 'correct-horse-battery',
    ])->assertRedirect(route('users.index'))->assertStatus(303);

    $user = User::query()->where('email', 'manager@example.test')->firstOrFail();

    expect($user->office_id)->toBe($office->id)
        ->and($user->role)->toBe(Role::AccountManager)
        ->and($user->email_verified_at)->not->toBeNull()
        ->and(Hash::check('correct-horse-battery', $user->password))->toBeTrue();
});

test('the owner cannot deactivate their own account', function () {
    $owner = $this->actingAsRole(Role::Owner);

    $this->patch(route('users.update', $owner), [
        'name' => $owner->name,
        'email' => $owner->email,
        'role' => Role::Owner->value,
        'office_id' => null,
        'active' => false,
    ])->assertSessionHasErrors('active');

    expect($owner->fresh()->active)->toBeTrue();
});

test('the last active owner cannot lose the owner role', function () {
    $owner = $this->actingAsRole(Role::Owner);
    $other = User::factory()->finance()->create();

    $this->patch(route('users.update', $other), [
        'name' => $other->name,
        'email' => $other->email,
        'role' => Role::Finance->value,
        'office_id' => null,
        'active' => true,
    ])->assertRedirect(route('users.index'));

    $this->patch(route('users.update', $owner), [
        'name' => $owner->name,
        'email' => $owner->email,
        'role' => Role::Finance->value,
        'office_id' => null,
        'active' => true,
    ])->assertSessionHasErrors('role');

    expect($owner->fresh()->role)->toBe(Role::Owner);
});

test('the owner can send a password reset link', function () {
    $this->actingAsRole(Role::Owner);
    $user = User::factory()->create();
    Password::shouldReceive('broker->sendResetLink')->once()->andReturn(Password::RESET_LINK_SENT);

    $this->post(route('users.password-reset', $user))
        ->assertRedirect(route('users.index'))
        ->assertStatus(303);
});

test('the owner can set another user password', function () {
    $this->actingAsRole(Role::Owner);
    $user = User::factory()->create();

    $this->put(route('users.password', $user), [
        'password' => 'a-new-strong-pass-1',
        'password_confirmation' => 'a-new-strong-pass-1',
    ])->assertRedirect(route('users.index'))->assertStatus(303);

    expect(Hash::check('a-new-strong-pass-1', $user->fresh()->password))->toBeTrue();
});

test('non-owners cannot set passwords or delete users', function () {
    $this->actingAsRole(Role::Finance);
    $user = User::factory()->create();

    $this->put(route('users.password', $user), [
        'password' => 'a-new-strong-pass-1',
        'password_confirmation' => 'a-new-strong-pass-1',
    ])->assertForbidden();
    $this->delete(route('users.destroy', $user))->assertForbidden();
});

test('the owner can delete a user but not themselves', function () {
    $owner = $this->actingAsRole(Role::Owner);
    $user = User::factory()->create();

    $this->delete(route('users.destroy', $user))->assertRedirect(route('users.index'));
    $this->assertDatabaseMissing('users', ['id' => $user->id]);

    $this->delete(route('users.destroy', $owner))->assertForbidden();
    $this->assertDatabaseHas('users', ['id' => $owner->id]);
});

test('a user that clients still depend on is not deleted', function () {
    $this->actingAsRole(Role::Owner);
    $manager = User::factory()->create(['role' => Role::AccountManager]);
    Client::factory()->create(['account_manager_id' => $manager->id]);

    $this->delete(route('users.destroy', $manager))
        ->assertRedirect(route('users.index'))
        ->assertSessionHas('error');

    $this->assertDatabaseHas('users', ['id' => $manager->id]);
});

test('the owner can change settings and the change is audited', function () {
    $this->actingAsRole(Role::Owner);

    $this->get(route('settings.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/Settings')
            ->where('page.weekStart', config('khazna.week_starts_on')));

    $this->patch(route('settings.update'), [
        'week_start' => 'sunday',
        'reminder_template' => 'Hello {client}, {amount} is due on {date}.',
        'currency_label' => 'DA',
        'salary_due_day' => 25,
    ])->assertRedirect(route('settings.edit'))->assertStatus(303);

    expect(Setting::current(Setting::WEEK_START))->toBe('sunday')
        ->and(Setting::current(Setting::SALARY_DUE_DAY))->toBe('25');

    $this->assertDatabaseHas('activity_log', [
        'event' => 'created',
        'subject_type' => Setting::class,
    ]);
});

test('settings validate the week start and salary day', function () {
    $this->actingAsRole(Role::Owner);

    $this->patch(route('settings.update'), [
        'week_start' => 'friday',
        'reminder_template' => '',
        'currency_label' => '',
        'salary_due_day' => 29,
    ])->assertSessionHasErrors(['week_start', 'reminder_template', 'currency_label', 'salary_due_day']);
});

test('the audit log can be filtered by user and entity', function () {
    $owner = $this->actingAsRole(Role::Owner);
    $other = User::factory()->finance()->create(['name' => 'Sara Audit']);

    activity('audit')
        ->causedBy($owner)
        ->performedOn($other)
        ->event('updated')
        ->withProperties(['attributes' => ['name' => 'Sara Audit']])
        ->log('updated');

    $this->get(route('audit-log.index', ['user' => $owner->id, 'entity' => User::class]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/AuditLog')
            ->where('filters.user', $owner->id)
            ->where('filters.entity', User::class)
            ->has('rows', 2)
            ->where('rows.0.causerName', $owner->name));
});

test('the audit log can be filtered by date', function () {
    $this->actingAsRole(Role::Owner);
    $user = User::factory()->create();
    $today = AlgerianDate::today()->toDateString();

    activity('audit')
        ->performedOn($user)
        ->event('updated')
        ->withProperties(['attributes' => ['name' => 'Today']])
        ->log('updated');

    $todayCount = Activity::query()->count();

    $this->get(route('audit-log.index', ['from' => $today, 'to' => $today]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('total', $todayCount));

    $this->get(route('audit-log.index', ['from' => '2000-01-01', 'to' => '2000-01-01']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('total', 0));
});

test('unrestricted users see every office while restricted users see only theirs', function () {
    $first = Office::factory()->create();
    $second = Office::factory()->create();

    $this->actingAsRole(Role::Owner);
    $this->get(route('users.index'))->assertInertia(fn (Assert $page) => $page->has('offices', 2));

    $this->actingAsRole(Role::Finance, ['office_id' => $first->id]);
    $this->get(route('settings.edit'))->assertForbidden();
    $this->get(route('home'))->assertInertia(fn (Assert $page) => $page
        ->has('offices', 1)
        ->where('offices.0.id', $first->id));
});
