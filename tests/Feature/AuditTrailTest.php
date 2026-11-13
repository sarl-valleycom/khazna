<?php

use App\Enums\Role;
use App\Models\Activity;
use App\Models\Concerns\Auditable;
use App\Models\User;
use App\Support\AuditContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

class AuditableFixture extends Model
{
    use Auditable;

    protected $table = 'auditable_fixtures';

    protected $fillable = ['name', 'salary_cents'];

    public $timestamps = false;
}

beforeEach(function () {
    Schema::create('auditable_fixtures', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->unsignedInteger('salary_cents')->nullable();
    });
});

afterEach(function () {
    Schema::dropIfExists('auditable_fixtures');
});

test('an update logs only dirty attributes with old and new values', function () {
    $fixture = AuditableFixture::create(['name' => 'Before', 'salary_cents' => 100000]);
    $fixture->update(['name' => 'After', 'salary_cents' => 250000]);

    $activity = Activity::query()->latest('id')->firstOrFail();

    expect($activity->event)->toBe('updated')
        ->and($activity->getProperty('reason'))->toBeNull()
        ->and($activity->attribute_changes['attributes'])->toMatchArray([
            'name' => 'After',
            'salary_cents' => 250000,
        ])
        ->and($activity->attribute_changes['old'])->toMatchArray([
            'name' => 'Before',
            'salary_cents' => 100000,
        ]);
});

test('an audited user change masks sensitive fields but keeps role changes', function () {
    $user = User::factory()->create();

    $user->forceFill([
        'password' => 'a-new-password',
        'role' => Role::Finance,
        'remember_token' => 'a-token',
    ])->save();

    $activity = Activity::query()
        ->where('subject_type', User::class)
        ->where('event', 'updated')
        ->latest('id')
        ->firstOrFail();

    expect($activity->attribute_changes['attributes'])
        ->toHaveKey('role')
        ->not->toHaveKey('password')
        ->not->toHaveKey('remember_token')
        ->not->toHaveKey('two_factor_secret')
        ->not->toHaveKey('two_factor_recovery_codes');
});

test('the request-scoped reason is attached to the activity', function () {
    app(AuditContext::class)->setReason('Correction requested by the owner');
    $fixture = AuditableFixture::create(['name' => 'Named', 'salary_cents' => 50000]);
    $fixture->update(['name' => 'Renamed']);

    $activity = Activity::query()->latest('id')->firstOrFail();

    expect($activity->getProperty('reason'))->toBe('Correction requested by the owner');
});

test('the reason helper restores the previous value', function () {
    $context = app(AuditContext::class);
    $context->setReason('outer');

    $context->withReason('inner', function () use ($context): void {
        expect($context->reason())->toBe('inner');
    });

    expect($context->reason())->toBe('outer');
});

test('only the owner may view the audit log screen', function () {
    expect(User::factory()->owner()->create()->can('view-audit-log'))->toBeTrue()
        ->and(User::factory()->finance()->create()->can('view-audit-log'))->toBeFalse()
        ->and(User::factory()->accountManager()->create()->can('view-audit-log'))->toBeFalse();
});

test('the audit log refuses updates and deletes from application code', function () {
    AuditableFixture::create(['name' => 'Kept', 'salary_cents' => 100]);
    $activity = Activity::query()->firstOrFail();

    expect(fn () => $activity->update(['description' => 'tampered']))
        ->toThrow(LogicException::class)
        ->and(fn () => $activity->delete())
        ->toThrow(LogicException::class);

    expect(Activity::query()->count())->toBe(1);
});
