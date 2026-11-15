<?php

use App\Enums\ContractStatus;
use App\Enums\ContractType;
use App\Enums\Cycle;
use App\Enums\Direction;
use App\Enums\Role;
use App\Models\Activity;
use App\Models\Client;
use App\Models\Contract;
use App\Models\Office;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/**
 * @return array<string, mixed>
 */
function contractPayload(Client $client, array $overrides = []): array
{
    return [
        'client_id' => $client->id,
        'amount_cents' => 4_500_000,
        'cycle' => Cycle::Monthly->value,
        'starts_on' => '2026-01-15',
        'ends_on' => null,
        'auto_renew' => true,
        'notes' => 'Renewed yearly.',
        'office_id' => $client->office_id,
        ...$overrides,
    ];
}

test('guests are redirected from the contract pages', function () {
    $contract = Contract::factory()->create();

    $this->get(route('contracts.index'))->assertRedirect(route('login'));
    $this->get(route('contracts.show', $contract))->assertRedirect(route('login'));
    $this->get(route('contracts.attachment', $contract))->assertRedirect(route('login'));
});

test('roles only see contracts in their scope', function () {
    $office = Office::factory()->create();
    $otherOffice = Office::factory()->create();
    $manager = User::factory()->accountManager()->forOffice($office->id)->create();
    $otherManager = User::factory()->accountManager()->forOffice($otherOffice->id)->create();
    $client = Client::factory()->forManager($manager)->forOffice($office)->create();
    $otherClient = Client::factory()->forManager($otherManager)->forOffice($otherOffice)->create();
    $contract = Contract::factory()->create(['client_id' => $client->id, 'office_id' => $office->id]);
    $otherContract = Contract::factory()->create(['client_id' => $otherClient->id, 'office_id' => $otherOffice->id]);

    $this->actingAsRole(Role::Owner);

    $this->get(route('contracts.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('contracts/Index')
            ->has('contracts', 2));

    $this->actingAsRole(Role::Finance, ['office_id' => $office->id]);

    $this->get(route('contracts.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('contracts', 1)
            ->where('contracts.0.id', $contract->id));

    $this->get(route('contracts.show', $otherContract))->assertForbidden();

    $this->actingAs($manager);

    $this->get(route('contracts.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('contracts', 1)
            ->where('contracts.0.id', $contract->id));

    $this->get(route('contracts.show', $otherContract))->assertForbidden();
});

test('the owner creates a SaaS renewal with fixed type direction and status', function () {
    $this->actingAsRole(Role::Owner);
    $office = Office::factory()->create();
    $manager = User::factory()->accountManager()->forOffice($office->id)->create();
    $client = Client::factory()->forManager($manager)->forOffice($office)->create();

    $response = $this->post(route('contracts.store'), contractPayload($client, [
        'notes' => '  Signed renewal.  ',
    ]))->assertStatus(303);

    $contract = Contract::query()->firstOrFail();

    $response->assertRedirect(route('contracts.show', $contract));

    expect($contract->type)->toBe(ContractType::SaasRenewal)
        ->and($contract->direction)->toBe(Direction::In)
        ->and($contract->status)->toBe(ContractStatus::Active)
        ->and($contract->amount_cents)->toBe(4_500_000)
        ->and($contract->cycle)->toBe(Cycle::Monthly)
        ->and($contract->starts_on->toDateString())->toBe('2026-01-15')
        ->and($contract->ends_on)->toBeNull()
        ->and($contract->auto_renew)->toBeTrue()
        ->and($contract->notes)->toBe('Signed renewal.')
        ->and($contract->client_id)->toBe($client->id)
        ->and($contract->office_id)->toBe($office->id)
        ->and(Activity::query()->where('subject_type', Contract::class)->count())->toBe(1);
});

test('contract validation rejects bad amounts cycles dates and attachments', function () {
    $this->actingAsRole(Role::Owner);
    $office = Office::factory()->create();
    $manager = User::factory()->accountManager()->forOffice($office->id)->create();
    $client = Client::factory()->forManager($manager)->forOffice($office)->create();

    $this->post(route('contracts.store'), contractPayload($client, [
        'amount_cents' => 0,
        'cycle' => Cycle::Once->value,
        'starts_on' => '2026-02-01',
        'ends_on' => '2026-01-01',
        'attachment' => UploadedFile::fake()->create('malware.php', 10, 'text/x-php'),
    ]))->assertSessionHasErrors(['amount_cents', 'cycle', 'ends_on', 'attachment']);

    $this->post(route('contracts.store'), contractPayload($client, [
        'client_id' => 999999,
        'starts_on' => 'not-a-date',
    ]))->assertSessionHasErrors(['client_id', 'starts_on']);

    expect(Contract::query()->count())->toBe(0);
});

test('an account manager creates contracts for their own clients and office only', function () {
    $office = Office::factory()->create();
    $otherOffice = Office::factory()->create();
    $manager = $this->actingAsRole(Role::AccountManager, ['office_id' => $office->id]);
    $client = Client::factory()->forManager($manager)->forOffice($office)->create();
    $foreignClient = Client::factory()->forOffice($otherOffice)->create();

    $this->post(route('contracts.store'), contractPayload($client, [
        'office_id' => $otherOffice->id,
    ]))->assertSessionHasNoErrors()->assertStatus(303);

    expect(Contract::query()->firstOrFail()->office_id)->toBe($office->id);

    $this->post(route('contracts.store'), contractPayload($foreignClient))
        ->assertSessionHasErrors('client_id');

    expect(Contract::query()->count())->toBe(1);
});

test('a contract needs an office when neither the actor nor the client has one', function () {
    $this->actingAsRole(Role::Owner);
    $client = Client::factory()->create(['office_id' => null]);

    $this->post(route('contracts.store'), contractPayload($client, [
        'office_id' => null,
    ]))->assertSessionHasErrors('office_id');

    expect(Contract::query()->count())->toBe(0);
});

test('a contract can be updated without changing its client or type', function () {
    $this->actingAsRole(Role::Owner);
    $office = Office::factory()->create();
    $manager = User::factory()->accountManager()->forOffice($office->id)->create();
    $client = Client::factory()->forManager($manager)->forOffice($office)->create();
    $contract = Contract::factory()->create([
        'client_id' => $client->id,
        'office_id' => $office->id,
        'amount_cents' => 1_000_000,
        'cycle' => Cycle::Monthly,
    ]);
    $otherClient = Client::factory()->forOffice($office)->create();

    $this->patch(route('contracts.update', $contract), contractPayload($contract->client, [
        'client_id' => $otherClient->id,
    ]))->assertSessionHasErrors('client_id');

    $this->patch(route('contracts.update', $contract), contractPayload($contract->client, [
        'amount_cents' => 2_500_000,
        'cycle' => Cycle::Yearly->value,
        'ends_on' => '2026-12-31',
        'auto_renew' => false,
    ]))->assertRedirect(route('contracts.show', $contract))->assertStatus(303);

    $contract->refresh();

    expect($contract->amount_cents)->toBe(2_500_000)
        ->and($contract->cycle)->toBe(Cycle::Yearly)
        ->and($contract->ends_on?->toDateString())->toBe('2026-12-31')
        ->and($contract->auto_renew)->toBeFalse()
        ->and($contract->type)->toBe(ContractType::SaasRenewal)
        ->and($contract->direction)->toBe(Direction::In)
        ->and($contract->status)->toBe(ContractStatus::Active)
        ->and(Activity::query()->where('subject_type', Contract::class)->where('event', 'updated')->count())->toBe(1);
});

test('contract status transitions require a reason and are audited', function () {
    $this->actingAsRole(Role::Owner);
    $contract = Contract::factory()->create(['status' => ContractStatus::Active]);

    $this->patch(route('contracts.status', $contract), ['status' => 'paused'])
        ->assertSessionHasErrors('reason');

    $this->patch(route('contracts.status', $contract), [
        'status' => 'paused',
        'reason' => 'Client asked us to pause.',
    ])->assertStatus(303);

    expect($contract->refresh()->status)->toBe(ContractStatus::Paused);

    $activity = Activity::query()
        ->where('subject_type', Contract::class)
        ->where('subject_id', $contract->id)
        ->where('event', 'updated')
        ->latest('id')
        ->firstOrFail();

    expect($activity->getProperty('reason'))->toBe('Client asked us to pause.');

    $this->patch(route('contracts.status', $contract), ['status' => 'active'])
        ->assertStatus(303);

    expect($contract->refresh()->status)->toBe(ContractStatus::Active);

    $this->patch(route('contracts.status', $contract), [
        'status' => 'canceled',
        'reason' => 'Contract ended.',
    ])->assertStatus(303);

    expect($contract->refresh()->status)->toBe(ContractStatus::Canceled);

    $this->patch(route('contracts.status', $contract), ['status' => 'active'])
        ->assertSessionHasErrors('status');

    expect($contract->refresh()->status)->toBe(ContractStatus::Canceled);
});

test('another account manager cannot change a contract status', function () {
    $office = Office::factory()->create();
    $contract = Contract::factory()->create(['office_id' => $office->id]);
    $this->actingAsRole(Role::AccountManager, ['office_id' => $office->id]);

    $this->patch(route('contracts.status', $contract), [
        'status' => 'paused',
        'reason' => 'Nope.',
    ])->assertForbidden();
});

test('contracts can be searched and filtered by status and office', function () {
    $this->actingAsRole(Role::Owner);
    $office = Office::factory()->create();
    $otherOffice = Office::factory()->create();
    $client = Client::factory()->forOffice($office)->create(['name' => 'Alpha Telecom']);
    $otherClient = Client::factory()->forOffice($otherOffice)->create(['name' => 'Beta Hosting']);
    $match = Contract::factory()->create([
        'client_id' => $client->id,
        'office_id' => $office->id,
        'status' => ContractStatus::Paused,
    ]);
    Contract::factory()->create([
        'client_id' => $otherClient->id,
        'office_id' => $otherOffice->id,
    ]);

    $this->get(route('contracts.index', ['q' => 'alpha']))
        ->assertInertia(fn (Assert $page) => $page->has('contracts', 1)->where('contracts.0.id', $match->id));

    $this->get(route('contracts.index', ['status' => 'paused']))
        ->assertInertia(fn (Assert $page) => $page->has('contracts', 1)->where('contracts.0.id', $match->id));

    $this->get(route('contracts.index', ['office' => $otherOffice->id]))
        ->assertInertia(fn (Assert $page) => $page->has('contracts', 1)->where('contracts.0.officeId', $otherOffice->id));
});

test('an attachment is stored privately and served safely', function () {
    Storage::fake('local');
    $this->actingAsRole(Role::Owner);
    $office = Office::factory()->create();
    $manager = User::factory()->accountManager()->forOffice($office->id)->create();
    $client = Client::factory()->forManager($manager)->forOffice($office)->create();

    $this->post(route('contracts.store'), contractPayload($client, [
        'attachment' => UploadedFile::fake()->create('signed.pdf', 100, 'application/pdf'),
    ]))->assertStatus(303);

    $contract = Contract::query()->firstOrFail();
    $oldPath = $contract->attachment_path;

    expect($oldPath)->not->toBeNull()
        ->and($contract->attachment_name)->toBe('signed.pdf')
        ->and($contract->attachment_mime)->toBe('application/pdf');

    Storage::disk('local')->assertExists($oldPath);

    $this->patch(route('contracts.update', $contract), contractPayload($contract->client, [
        'attachment' => UploadedFile::fake()->create('renewal.pdf', 100, 'application/pdf'),
    ]))->assertRedirect(route('contracts.show', $contract))->assertStatus(303);

    $contract->refresh();

    expect($contract->attachment_path)->not->toBe($oldPath)
        ->and($contract->attachment_name)->toBe('renewal.pdf');

    Storage::disk('local')->assertMissing($oldPath);
    Storage::disk('local')->assertExists($contract->attachment_path);

    $this->get(route('contracts.attachment', $contract))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf')
        ->assertHeader('x-content-type-options', 'nosniff');
});

test('a client page lists its visible contracts', function () {
    $office = Office::factory()->create();
    $manager = $this->actingAsRole(Role::AccountManager, ['office_id' => $office->id]);
    $client = Client::factory()->forManager($manager)->forOffice($office)->create();
    $contract = Contract::factory()->create([
        'client_id' => $client->id,
        'office_id' => $office->id,
    ]);

    $this->get(route('clients.show', $client))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('contracts', 1)
            ->where('contracts.0.id', $contract->id)
            ->where('contracts.0.clientName', $client->name));
});

test('contracts cannot be permanently deleted', function () {
    $contract = Contract::factory()->create();

    expect(fn () => $contract->forceDelete())->toThrow(LogicException::class)
        ->and(Contract::query()->whereKey($contract->id)->exists())->toBeTrue();
});

test('the contract form can create and update a client contract with the null ids it always sends', function () {
    $this->actingAsRole(Role::Owner);
    $office = Office::factory()->create();
    $manager = User::factory()->accountManager()->forOffice($office->id)->create();
    $client = Client::factory()->forManager($manager)->forOffice($office)->create();

    // Exactly what the browser form posts: every id key is present, the unused ones as null,
    // and an empty milestones list for a type that has none.
    $formPayload = contractPayload($client, [
        'type' => ContractType::SaasRenewal->value,
        'supplier_id' => null,
        'category_id' => null,
        'milestones' => [],
    ]);

    $this->post(route('contracts.store'), $formPayload)
        ->assertSessionHasNoErrors()
        ->assertStatus(303);

    $contract = Contract::query()->sole();

    $this->patch(route('contracts.update', $contract), [...$formPayload, 'amount_cents' => 5_000_000])
        ->assertSessionHasNoErrors()
        ->assertStatus(303);

    expect($contract->refresh()->amount_cents)->toBe(5_000_000);
});

test('a client contract still rejects a supplier or category', function () {
    $this->actingAsRole(Role::Owner);
    $client = Client::factory()->create();

    $this->post(route('contracts.store'), contractPayload($client, ['supplier_id' => 1, 'category_id' => 1]))
        ->assertSessionHasErrors(['supplier_id', 'category_id']);
});
