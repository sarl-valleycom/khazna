<?php

use App\Enums\Role;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

test('the demo seeder creates the four role/office accounts', function () {
    $this->seed(DemoSeeder::class);

    $owner = User::query()->where('email', 'owner@khazna.test')->firstOrFail();
    $finance = User::query()->where('email', 'finance@khazna.test')->firstOrFail();
    $algiers = User::query()->where('email', 'algiers@khazna.test')->firstOrFail();
    $oran = User::query()->where('email', 'oran@khazna.test')->firstOrFail();

    expect(User::query()->count())->toBe(4)
        ->and($owner->role)->toBe(Role::Owner)
        ->and($owner->office_id)->toBeNull()
        ->and($finance->role)->toBe(Role::Finance)
        ->and($finance->office_id)->toBe(1)
        ->and($algiers->role)->toBe(Role::AccountManager)
        ->and($algiers->office_id)->toBe(1)
        ->and($oran->role)->toBe(Role::AccountManager)
        ->and($oran->office_id)->toBe(2);
});

test('the demo seeder refuses to run in production', function () {
    $this->app->detectEnvironment(fn (): string => 'production');

    expect(fn () => (new DemoSeeder)->run())
        ->toThrow(RuntimeException::class);
});

test('the demo seeder refuses the default password outside local and testing', function () {
    $this->app->detectEnvironment(fn (): string => 'staging');
    config()->set('demo.password', null);

    expect(fn () => (new DemoSeeder)->run())->toThrow(RuntimeException::class);

    config()->set('demo.password', 'password');

    expect(fn () => (new DemoSeeder)->run())->toThrow(RuntimeException::class);
});

test('the demo seeder accepts an explicit password on staging', function () {
    $this->app->detectEnvironment(fn (): string => 'staging');
    config()->set('demo.password', 'a-long-unguessable-passphrase');

    $this->seed(DemoSeeder::class);

    $owner = User::query()->where('email', 'owner@khazna.test')->firstOrFail();

    expect(Hash::check('a-long-unguessable-passphrase', $owner->password))->toBeTrue()
        ->and(Hash::check('password', $owner->password))->toBeFalse();
});
