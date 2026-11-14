<?php

use App\Enums\Role;
use App\Models\Client;
use App\Models\Contract;
use App\Models\Due;
use App\Models\Office;
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

    $algiersOffice = Office::query()->where('name', 'Algiers HQ')->firstOrFail();
    $oranOffice = Office::query()->where('name', 'Oran Office')->firstOrFail();

    expect(Office::query()->count())->toBe(2)
        ->and($algiersOffice->city)->toBe('Algiers')
        ->and($oranOffice->city)->toBe('Oran')
        ->and(User::query()->count())->toBe(4)
        ->and(Client::query()->count())->toBe(3)
        ->and(Contract::query()->count())->toBe(3)
        ->and($owner->role)->toBe(Role::Owner)
        ->and($owner->office_id)->toBeNull()
        ->and($finance->role)->toBe(Role::Finance)
        ->and($finance->office_id)->toBe($algiersOffice->id)
        ->and($algiers->role)->toBe(Role::AccountManager)
        ->and($algiers->office_id)->toBe($algiersOffice->id)
        ->and($oran->role)->toBe(Role::AccountManager)
        ->and($oran->office_id)->toBe($oranOffice->id)
        ->and(Client::query()->where('office_id', $algiersOffice->id)->count())->toBe(2)
        ->and(Client::query()->where('office_id', $oranOffice->id)->count())->toBe(1)
        ->and(Contract::query()->where('office_id', $algiersOffice->id)->count())->toBe(2)
        ->and(Contract::query()->where('office_id', $oranOffice->id)->count())->toBe(1)
        ->and(Due::query()->count())->toBeGreaterThanOrEqual(1)
        ->and(Due::query()->where('status', 'canceled')->count())->toBe(0);
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
