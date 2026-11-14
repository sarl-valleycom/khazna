<?php

use App\Actions\Dues\GenerateDues;
use App\Enums\DueKind;
use App\Enums\DueStatus;
use App\Enums\Role;
use App\Models\Activity;
use App\Models\Client;
use App\Models\Contract;
use App\Models\Due;
use App\Models\Office;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

afterEach(function () {
    CarbonImmutable::setTestNow();
});

/**
 * @return array<string, mixed>
 */
function manualDuePayload(array $overrides = []): array
{
    return [
        'direction' => 'out',
        'kind' => DueKind::Expense->value,
        'due_on' => '2026-03-01',
        'amount_cents' => 123_450,
        'description' => '  Office rent  ',
        ...$overrides,
    ];
}

/**
 * @return array<string, mixed>
 */
function duesContractPayload(Client $client, array $overrides = []): array
{
    return [
        'client_id' => $client->id,
        'amount_cents' => 4_500_000,
        'cycle' => 'monthly',
        'starts_on' => '2026-01-15',
        'ends_on' => null,
        'auto_renew' => true,
        'notes' => null,
        'office_id' => $client->office_id,
        ...$overrides,
    ];
}

function dueFor(Contract $contract, string $dueOn, array $overrides = []): Due
{
    return Due::factory()->create([
        'contract_id' => $contract->id,
        'client_id' => $contract->client_id,
        'office_id' => $contract->office_id,
        'period' => $dueOn,
        'due_on' => $dueOn,
        'kind' => DueKind::Saas,
        'direction' => $contract->direction,
        'amount_due_cents' => $contract->amount_cents,
        ...$overrides,
    ]);
}

test('guests are redirected from due actions', function () {
    $due = Due::factory()->create();

    $this->post(route('dues.store', $due->client_id), manualDuePayload())
        ->assertRedirect(route('login'));
    $this->patch(route('dues.postpone', $due), [
        'new_due_on' => '2026-03-01',
        'reason' => 'Late.',
    ])->assertRedirect(route('login'));
    $this->patch(route('dues.cancel', $due), ['reason' => 'No longer owed.'])
        ->assertRedirect(route('login'));
});

test('dues are generated from the anchored cycle for the next thirty days only', function () {
    CarbonImmutable::setTestNow('2026-02-01 12:00:00');

    $office = Office::factory()->create();
    $client = Client::factory()->forOffice($office)->create();
    $contract = Contract::factory()->create([
        'client_id' => $client->id,
        'office_id' => $office->id,
        'amount_cents' => 4_500_000,
        'cycle' => 'monthly',
        'starts_on' => '2026-01-31',
        'ends_on' => null,
    ]);

    $report = app(GenerateDues::class)->forContract($contract);

    expect($report->created)->toBe(1)
        ->and($report->skipped)->toBe(0)
        ->and($report->contractsTouched())->toBe(1);

    $due = Due::query()->sole();

    expect($due->period->toDateString())->toBe('2026-02-28')
        ->and($due->due_on->toDateString())->toBe('2026-02-28')
        ->and($due->amount_due_cents)->toBe(4_500_000)
        ->and($due->amount_paid_cents)->toBe(0)
        ->and($due->status)->toBe(DueStatus::Pending)
        ->and($due->client_id)->toBe($client->id)
        ->and($due->office_id)->toBe($office->id)
        ->and($due->kind)->toBe(DueKind::Saas);

    $second = app(GenerateDues::class)->forContract($contract);

    expect($second->created)->toBe(0)
        ->and($second->skipped)->toBe(1)
        ->and(Due::query()->count())->toBe(1);
});

test('generation skips paused ended and fully past contracts but respects ends on', function () {
    CarbonImmutable::setTestNow('2026-02-01 12:00:00');

    $office = Office::factory()->create();
    $client = Client::factory()->forOffice($office)->create();
    $generate = app(GenerateDues::class);

    $paused = Contract::factory()->create([
        'client_id' => $client->id,
        'office_id' => $office->id,
        'status' => 'paused',
        'starts_on' => '2026-01-15',
    ]);
    $ended = Contract::factory()->create([
        'client_id' => $client->id,
        'office_id' => $office->id,
        'ends_on' => '2026-01-31',
        'starts_on' => '2025-12-15',
        'auto_renew' => false,
    ]);
    $endingSoon = Contract::factory()->create([
        'client_id' => $client->id,
        'office_id' => $office->id,
        'ends_on' => '2026-02-20',
        'starts_on' => '2026-01-15',
        'cycle' => 'monthly',
        'auto_renew' => false,
    ]);

    expect($generate->forContract($paused)->created)->toBe(0)
        ->and($generate->forContract($ended)->created)->toBe(0)
        ->and($generate->forContract($endingSoon)->created)->toBe(1);

    expect(Due::query()->sole()->due_on->toDateString())->toBe('2026-02-15');
});

test('creating a contract generates its first dues immediately', function () {
    CarbonImmutable::setTestNow('2026-02-01 12:00:00');

    $this->actingAsRole(Role::Owner);
    $office = Office::factory()->create();
    $manager = User::factory()->accountManager()->forOffice($office->id)->create();
    $client = Client::factory()->forManager($manager)->forOffice($office)->create();

    $this->post(route('contracts.store'), duesContractPayload($client))
        ->assertStatus(303);

    $contract = Contract::query()->sole();

    expect($contract->dues()->count())->toBe(1)
        ->and($contract->dues()->sole()->due_on->toDateString())->toBe('2026-02-15');
});

test('pausing a contract cancels only open future dues and records the reason', function () {
    CarbonImmutable::setTestNow('2026-02-01 12:00:00');

    $owner = $this->actingAsRole(Role::Owner);
    $office = Office::factory()->create();
    $manager = User::factory()->accountManager()->forOffice($office->id)->create();
    $client = Client::factory()->forManager($manager)->forOffice($office)->create();
    $contract = Contract::factory()->create([
        'client_id' => $client->id,
        'office_id' => $office->id,
    ]);

    $past = dueFor($contract, '2026-01-10');
    $future = dueFor($contract, '2026-02-15');
    $futurePartial = dueFor($contract, '2026-03-15', [
        'status' => DueStatus::Partial,
        'amount_paid_cents' => 100_000,
    ]);
    $futurePaid = dueFor($contract, '2026-04-15', ['status' => DueStatus::Paid]);
    $futureCanceled = dueFor($contract, '2026-05-15', ['status' => DueStatus::Canceled]);

    $this->patch(route('contracts.status', $contract), [
        'status' => 'paused',
        'reason' => 'Client is restructuring.',
    ])->assertStatus(303);

    expect($contract->refresh()->status->value)->toBe('paused')
        ->and($past->refresh()->status)->toBe(DueStatus::Pending)
        ->and($future->refresh()->status)->toBe(DueStatus::Canceled)
        ->and($future->canceled_reason)->toBe('Client is restructuring.')
        ->and($futurePartial->refresh()->status)->toBe(DueStatus::Canceled)
        ->and($futurePaid->refresh()->status)->toBe(DueStatus::Paid)
        ->and($futureCanceled->refresh()->canceled_reason)->toBeNull();

    $activity = Activity::query()
        ->where('subject_type', Due::class)
        ->where('subject_id', $future->id)
        ->where('event', 'updated')
        ->latest('id')
        ->firstOrFail();

    expect($activity->getProperty('reason'))->toBe('Client is restructuring.');
});

test('a manual due is scoped to the client office and can be created without a contract', function () {
    $this->actingAsRole(Role::Owner);
    $office = Office::factory()->create();
    $manager = User::factory()->accountManager()->forOffice($office->id)->create();
    $client = Client::factory()->forManager($manager)->forOffice($office)->create();

    $this->post(route('dues.store', $client), manualDuePayload())
        ->assertStatus(303);

    $due = Due::query()->sole();

    expect($due->contract_id)->toBeNull()
        ->and($due->client_id)->toBe($client->id)
        ->and($due->office_id)->toBe($office->id)
        ->and($due->direction->value)->toBe('out')
        ->and($due->kind)->toBe(DueKind::Expense)
        ->and($due->period->toDateString())->toBe('2026-03-01')
        ->and($due->due_on->toDateString())->toBe('2026-03-01')
        ->and($due->amount_due_cents)->toBe(123_450)
        ->and($due->description)->toBe('Office rent')
        ->and($due->status)->toBe(DueStatus::Pending);
});

test('manual dues reject invalid amounts and foreign clients', function () {
    $office = Office::factory()->create();
    $otherOffice = Office::factory()->create();
    $manager = $this->actingAsRole(Role::AccountManager, ['office_id' => $office->id]);
    $client = Client::factory()->forManager($manager)->forOffice($office)->create();
    $foreign = Client::factory()->forOffice($otherOffice)->create();

    $this->post(route('dues.store', $client), manualDuePayload([
        'amount_cents' => 0,
        'kind' => 'nope',
    ]))->assertSessionHasErrors(['amount_cents', 'kind']);

    $this->post(route('dues.store', $foreign), manualDuePayload())
        ->assertForbidden();

    expect(Due::query()->count())->toBe(0);
});

test('postponing a due records history and requires a future date', function () {
    CarbonImmutable::setTestNow('2026-02-01 12:00:00');

    $owner = $this->actingAsRole(Role::Owner);
    $office = Office::factory()->create();
    $manager = User::factory()->accountManager()->forOffice($office->id)->create();
    $client = Client::factory()->forManager($manager)->forOffice($office)->create();
    $contract = Contract::factory()->create([
        'client_id' => $client->id,
        'office_id' => $office->id,
    ]);
    $due = dueFor($contract, '2026-02-10');

    $this->patch(route('dues.postpone', $due), [
        'new_due_on' => '2026-01-20',
        'reason' => 'Too early.',
    ])->assertSessionHasErrors('new_due_on');

    $this->patch(route('dues.postpone', $due), [
        'new_due_on' => '2026-02-20',
        'reason' => '  Client asked for more time.  ',
    ])->assertStatus(303);

    $due->refresh();
    $history = $due->postponements()->sole();

    expect($due->due_on->toDateString())->toBe('2026-02-20')
        ->and($history->old_due_on->toDateString())->toBe('2026-02-10')
        ->and($history->new_due_on->toDateString())->toBe('2026-02-20')
        ->and($history->reason)->toBe('Client asked for more time.')
        ->and($history->user_id)->toBe($owner->id);

    $activity = Activity::query()
        ->where('subject_type', Due::class)
        ->where('subject_id', $due->id)
        ->where('event', 'updated')
        ->latest('id')
        ->firstOrFail();

    expect($activity->getProperty('reason'))->toBe('Client asked for more time.');
});

test('canceling a due stores the reason and is audited', function () {
    $this->actingAsRole(Role::Owner);
    $office = Office::factory()->create();
    $manager = User::factory()->accountManager()->forOffice($office->id)->create();
    $client = Client::factory()->forManager($manager)->forOffice($office)->create();
    $contract = Contract::factory()->create([
        'client_id' => $client->id,
        'office_id' => $office->id,
    ]);
    $due = dueFor($contract, '2026-02-15');

    $this->patch(route('dues.cancel', $due), ['reason' => 'Invoice issued in error.'])
        ->assertStatus(303);

    $due->refresh();

    expect($due->status)->toBe(DueStatus::Canceled)
        ->and($due->canceled_reason)->toBe('Invoice issued in error.');

    $activity = Activity::query()
        ->where('subject_type', Due::class)
        ->where('subject_id', $due->id)
        ->where('event', 'updated')
        ->latest('id')
        ->firstOrFail();

    expect($activity->getProperty('reason'))->toBe('Invoice issued in error.');
});

test('due actions are limited by office and account manager scope', function () {
    $office = Office::factory()->create();
    $otherOffice = Office::factory()->create();
    $manager = User::factory()->accountManager()->forOffice($office->id)->create();
    $otherManager = User::factory()->accountManager()->forOffice($office->id)->create();
    $client = Client::factory()->forManager($manager)->forOffice($office)->create();
    $otherClient = Client::factory()->forManager($otherManager)->forOffice($office)->create();
    $contract = Contract::factory()->create([
        'client_id' => $client->id,
        'office_id' => $office->id,
    ]);
    $otherContract = Contract::factory()->create([
        'client_id' => $otherClient->id,
        'office_id' => $office->id,
    ]);
    $due = dueFor($contract, '2026-02-15');
    $otherDue = dueFor($otherContract, '2026-02-15');

    $this->actingAsRole(Role::Finance, ['office_id' => $otherOffice->id]);
    $this->patch(route('dues.cancel', $due), ['reason' => 'No.'])
        ->assertForbidden();

    $this->actingAs($manager);
    $this->patch(route('dues.cancel', $otherDue), ['reason' => 'No.'])
        ->assertForbidden();

    $this->patch(route('dues.cancel', $due), ['reason' => 'Valid.'])
        ->assertStatus(303);
});

test('client and contract pages list dues in their scopes', function () {
    $office = Office::factory()->create();
    $manager = $this->actingAsRole(Role::AccountManager, ['office_id' => $office->id]);
    $client = Client::factory()->forManager($manager)->forOffice($office)->create();
    $contract = Contract::factory()->create([
        'client_id' => $client->id,
        'office_id' => $office->id,
    ]);
    $pending = dueFor($contract, '2026-02-15');
    $partial = dueFor($contract, '2026-03-15', [
        'status' => DueStatus::Partial,
        'amount_paid_cents' => 100_000,
    ]);
    $paid = dueFor($contract, '2026-04-15', ['status' => DueStatus::Paid]);

    $this->get(route('clients.show', $client))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('clients/Show')
            ->has('openDues', 2)
            ->where('openDues.0.id', $pending->id)
            ->where('openDues.1.id', $partial->id)
            ->where('canCreateDue', true));

    $this->get(route('contracts.show', $contract))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('contracts/Show')
            ->has('dues', 3)
            ->where('dues.0.id', $pending->id)
            ->where('dues.2.id', $paid->id));
});

test('the dues generate command logs a report', function () {
    CarbonImmutable::setTestNow('2026-02-01 12:00:00');

    $office = Office::factory()->create();
    $client = Client::factory()->forOffice($office)->create();
    Contract::factory()->create([
        'client_id' => $client->id,
        'office_id' => $office->id,
        'starts_on' => '2026-01-15',
        'cycle' => 'monthly',
    ]);

    $this->artisan('dues:generate')
        ->expectsOutputToContain('Created 1 dues for 1 contracts')
        ->assertSuccessful();
});

test('dues cannot be permanently deleted', function () {
    $due = Due::factory()->create();

    expect(fn () => $due->forceDelete())->toThrow(LogicException::class)
        ->and(Due::query()->whereKey($due->id)->exists())->toBeTrue();
});

test('SRS scenario 5: a postponed due shows its history with old date, new date, reason, user and time', function () {
    CarbonImmutable::setTestNow('2026-02-01 12:00:00');
    $office = Office::factory()->create();
    $manager = User::factory()->accountManager()->forOffice($office->id)->create(['name' => 'Amine']);
    $client = Client::factory()->forManager($manager)->forOffice($office)->create();
    $due = Due::factory()->forClient($client)->create(['due_on' => '2026-02-10', 'period' => '2026-02-10']);

    $this->actingAs($manager);
    $this->patch(route('dues.postpone', $due), ['new_due_on' => '2026-02-20', 'reason' => 'Client asked for more time.']);
    CarbonImmutable::setTestNow('2026-02-02 09:30:00');
    $this->patch(route('dues.postpone', $due), ['new_due_on' => '2026-02-25', 'reason' => 'Waiting on the bank.']);

    $this->get(route('clients.show', $client))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('openDues.0.postponements', 2)
            ->where('openDues.0.postponements.0.oldDueOn', '2026-02-10')
            ->where('openDues.0.postponements.0.newDueOn', '2026-02-20')
            ->where('openDues.0.postponements.0.reason', 'Client asked for more time.')
            ->where('openDues.0.postponements.0.userName', 'Amine')
            ->where('openDues.0.postponements.0.at', fn ($at) => str_starts_with((string) $at, '2026-02-01'))
            ->where('openDues.0.postponements.1.oldDueOn', '2026-02-20')
            ->where('openDues.0.postponements.1.newDueOn', '2026-02-25'));

    $this->get(route('home', ['tab' => 'month']))
        ->assertInertia(fn (Assert $page) => $page->has('dues.data.0.postponements', 2));
});
