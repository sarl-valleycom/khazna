<?php

use App\Actions\Dues\GenerateDues;
use App\Actions\Payments\RecordPayment;
use App\Data\PaymentInput;
use App\Enums\ContractType;
use App\Enums\Cycle;
use App\Enums\Direction;
use App\Enums\DueKind;
use App\Enums\DueStatus;
use App\Enums\PaymentMethod;
use App\Enums\Role;
use App\Models\Account;
use App\Models\Client;
use App\Models\Contract;
use App\Models\Due;
use App\Models\Milestone;
use App\Models\Office;
use App\Models\Setting;
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
function projectPayload(Client $client, array $overrides = []): array
{
    return [
        'type' => 'custom_project',
        'client_id' => $client->id,
        'office_id' => $client->office_id,
        'amount_cents' => 10_000_000,
        'starts_on' => '2026-03-01',
        'ends_on' => null,
        'notes' => null,
        'milestones' => [
            ['label' => 'Deposit', 'mode' => 'percent', 'value' => 40, 'trigger' => 'date', 'due_on' => '2026-03-10'],
            ['label' => 'Beta', 'mode' => 'percent', 'value' => 30, 'trigger' => 'delivery'],
            ['label' => 'Launch', 'mode' => 'percent', 'value' => 30, 'trigger' => 'delivery'],
        ],
        ...$overrides,
    ];
}

/** @return array{contract: Contract, client: Client} */
function makeProject(?User $creator = null): array
{
    $client = Client::factory()->forOffice(Office::factory()->create())->create();
    test()->actingAs($creator ?? User::factory()->owner()->create());
    test()->post(route('contracts.store'), projectPayload($client))->assertSessionHasNoErrors();

    return ['contract' => Contract::query()->sole(), 'client' => $client];
}

test('creating a project makes its milestones and one due for each', function () {
    CarbonImmutable::setTestNow('2026-03-01 09:00:00');
    ['contract' => $contract] = makeProject();

    $milestones = $contract->milestones()->with('due')->get();

    expect($contract->type)->toBe(ContractType::CustomProject)
        ->and($contract->cycle)->toBe(Cycle::Once)
        ->and($contract->direction)->toBe(Direction::In)
        ->and($milestones->pluck('amount_cents')->all())->toBe([4_000_000, 3_000_000, 3_000_000])
        ->and($milestones->pluck('label')->all())->toBe(['Deposit', 'Beta', 'Launch'])
        ->and($milestones->pluck('due.due_on')->map(fn ($date) => $date?->toDateString())->all())->toBe(['2026-03-10', null, null])
        ->and($milestones->pluck('due.kind')->unique()->all())->toBe([DueKind::Project])
        ->and($milestones->pluck('due.status')->unique()->all())->toBe([DueStatus::Pending])
        ->and($milestones->pluck('due.description')->all())->toBe(['Deposit', 'Beta', 'Launch']);
});

test('SRS scenario 7: a plan that does not add up is refused and nothing is created', function () {
    $client = Client::factory()->forOffice(Office::factory()->create())->create();
    $this->actingAsRole(Role::Owner);

    $bad = projectPayload($client);
    $bad['milestones'][2]['value'] = 20;

    $this->post(route('contracts.store'), $bad)->assertSessionHasErrors('milestones');

    $mixed = projectPayload($client);
    $mixed['milestones'][1] = ['label' => 'Beta', 'mode' => 'amount', 'value' => 3_000_000, 'trigger' => 'delivery'];
    $this->post(route('contracts.store'), $mixed)->assertSessionHasErrors('milestones');

    $noDate = projectPayload($client);
    unset($noDate['milestones'][0]['due_on']);
    $this->post(route('contracts.store'), $noDate)->assertSessionHasErrors('milestones.0.due_on');

    $none = projectPayload($client, ['milestones' => []]);
    $this->post(route('contracts.store'), $none)->assertSessionHasErrors('milestones');

    expect(Contract::query()->count())->toBe(0)->and(Milestone::query()->count())->toBe(0)->and(Due::query()->count())->toBe(0);
});

test('fixed-amount milestones must add up to the contract amount', function () {
    $client = Client::factory()->forOffice(Office::factory()->create())->create();
    $this->actingAsRole(Role::Owner);

    $payload = projectPayload($client, ['milestones' => [
        ['label' => 'Part 1', 'mode' => 'amount', 'value' => 6_000_000, 'trigger' => 'delivery'],
        ['label' => 'Part 2', 'mode' => 'amount', 'value' => 3_000_000, 'trigger' => 'delivery'],
    ]]);

    $this->post(route('contracts.store'), $payload)->assertSessionHasErrors('milestones');

    $payload['milestones'][1]['value'] = 4_000_000;
    $this->post(route('contracts.store'), $payload)->assertSessionHasNoErrors();

    expect(Milestone::query()->pluck('amount_cents')->all())->toBe([6_000_000, 4_000_000]);
});

test('the nightly run never schedules a project, and its amount is only changed by rebalancing', function () {
    CarbonImmutable::setTestNow('2026-03-05 09:00:00');
    ['contract' => $contract, 'client' => $client] = makeProject();

    expect(app(GenerateDues::class)->handle()->created)->toBe(0)
        ->and(app(GenerateDues::class)->forContract($contract)->created)->toBe(0)
        ->and(Due::query()->count())->toBe(3);

    $this->patch(route('contracts.update', $contract), [
        'type' => 'custom_project',
        'client_id' => $client->id,
        'office_id' => $client->office_id,
        'amount_cents' => 20_000_000,
        'starts_on' => '2026-03-01',
    ])->assertSessionHasErrors('amount_cents');

    expect(Due::query()->count())->toBe(3)->and($contract->refresh()->amount_cents)->toBe(10_000_000);
});

test('marking a delivery milestone delivered dates its due after the payment term', function () {
    CarbonImmutable::setTestNow('2026-03-20 09:00:00');
    ['contract' => $contract] = makeProject();
    Setting::put(Setting::MILESTONE_PAYMENT_TERM_DAYS, '15');
    $beta = $contract->milestones()->where('label', 'Beta')->sole();
    $deposit = $contract->milestones()->where('label', 'Deposit')->sole();

    $this->post(route('contracts.milestones.deliver', [$contract, $beta]))->assertSessionHasNoErrors();

    $beta->refresh();
    expect($beta->delivered_at->toDateString())->toBe('2026-03-20')
        ->and($beta->due_on->toDateString())->toBe('2026-04-04')
        ->and($beta->due->due_on->toDateString())->toBe('2026-04-04');

    // A second click is refused. A dated milestone keeps its own date when delivered.
    $this->post(route('contracts.milestones.deliver', [$contract, $beta]))->assertSessionHasErrors('milestone');
    $this->post(route('contracts.milestones.deliver', [$contract, $deposit]))->assertSessionHasNoErrors();
    expect($deposit->refresh()->due_on->toDateString())->toBe('2026-03-10')
        ->and($deposit->delivered_at->toDateString())->toBe('2026-03-20');
});

test('the payment term defaults to the delivery day and the owner can set it', function () {
    CarbonImmutable::setTestNow('2026-03-20 09:00:00');
    ['contract' => $contract] = makeProject();
    $launch = $contract->milestones()->where('label', 'Launch')->sole();

    $this->post(route('contracts.milestones.deliver', [$contract, $launch]));
    expect($launch->refresh()->due_on->toDateString())->toBe('2026-03-20');

    $this->actingAs(User::factory()->owner()->create())->patch(route('settings.update'), [
        'week_start' => 'saturday',
        'reminder_template' => 'Hello {client}',
        'currency_label' => 'DA',
        'salary_due_day' => 5,
        'milestone_payment_term_days' => 30,
    ])->assertSessionHasNoErrors();

    expect(Setting::current(Setting::MILESTONE_PAYMENT_TERM_DAYS))->toBe('30');
    $this->get(route('settings.edit'))->assertInertia(fn (Assert $page) => $page->where('page.milestonePaymentTermDays', 30));
});

test('marking a milestone as requested is recorded once', function () {
    CarbonImmutable::setTestNow('2026-03-20 09:00:00');
    ['contract' => $contract] = makeProject();
    $beta = $contract->milestones()->where('label', 'Beta')->sole();

    $this->post(route('contracts.milestones.request', [$contract, $beta]))->assertSessionHasNoErrors();
    expect($beta->refresh()->requested_at)->not->toBeNull();

    $this->post(route('contracts.milestones.request', [$contract, $beta]))->assertSessionHasErrors('milestone');
});

test('milestone actions are scoped and only exist on projects', function () {
    ['contract' => $contract, 'client' => $client] = makeProject();
    $milestone = $contract->milestones()->first();
    $stranger = User::factory()->accountManager()->forOffice($client->office_id)->create();
    $owner = User::factory()->owner()->create();

    $this->actingAs($stranger)->post(route('contracts.milestones.deliver', [$contract, $milestone]))->assertForbidden();
    $this->actingAs($stranger)->post(route('contracts.milestones.request', [$contract, $milestone]))->assertForbidden();
    $this->actingAs($stranger)->post(route('contracts.milestones.preview', $contract), ['milestones' => [
        ['id' => $milestone->id, 'label' => 'X', 'mode' => 'percent', 'value' => 100],
    ]])->assertForbidden();

    $saas = Contract::factory()->create(['client_id' => $client->id, 'office_id' => $client->office_id]);
    $this->actingAs($owner)->post(route('contracts.milestones.deliver', [$saas, $milestone]))->assertNotFound();

    $other = Milestone::query()->where('contract_id', '!=', $contract->id)->first();
    expect($other)->toBeNull();

    // The account manager of the client can act on their own project.
    $client->update(['account_manager_id' => $stranger->id]);
    $this->actingAs($stranger)->post(route('contracts.milestones.deliver', [$contract, $milestone]))->assertSessionHasNoErrors();
});

test('Needs attention lists delivered milestones nobody has asked the client to pay', function () {
    CarbonImmutable::setTestNow('2026-03-20 09:00:00');
    ['contract' => $contract] = makeProject();
    $beta = $contract->milestones()->where('label', 'Beta')->sole();
    $owner = User::factory()->owner()->create();

    $this->actingAs($owner)->post(route('contracts.milestones.deliver', [$contract, $beta]));

    $this->get(route('home', ['tab' => 'attention']))->assertInertia(fn (Assert $page) => $page
        ->has('attention', 1)
        ->where('attention.0.id', $contract->id)
        ->where('attention.0.reason', 'Milestone “Beta” was delivered on 20 Mar 2026, but the client has not been asked to pay.'));

    $this->post(route('contracts.milestones.request', [$contract, $beta]));

    $this->get(route('home', ['tab' => 'attention']))->assertInertia(fn (Assert $page) => $page->has('attention', 0));
});

test('milestones waiting for delivery sit on no time tab but show on the contract page', function () {
    CarbonImmutable::setTestNow('2026-03-20 09:00:00');
    ['contract' => $contract] = makeProject();
    $owner = User::factory()->owner()->create();

    $tabs = collect($this->actingAs($owner)->get(route('home'))->viewData('page')['props']['tabs'])->keyBy('value');
    // Only the dated deposit (10 March, overdue) is on a tab: 1 due, the two waiting ones are on none.
    expect($tabs['overdue']['count'])->toBe(1)
        ->and($tabs->only(['today', 'week', 'month'])->pluck('count')->sum())->toBe(0);

    $this->get(route('contracts.show', $contract))->assertInertia(fn (Assert $page) => $page
        ->has('milestones', 3)
        ->where('milestones.1.label', 'Beta')
        ->where('milestones.1.waitingForDelivery', true)
        ->where('milestones.1.dueOn', null)
        ->where('milestones.1.canDeliver', true)
        ->has('dues', 3)
        ->where('dues.2.dueOn', null));
});

test('a milestone due is paid like any other due', function () {
    CarbonImmutable::setTestNow('2026-03-20 09:00:00');
    ['contract' => $contract, 'client' => $client] = makeProject();
    $owner = User::factory()->owner()->create();
    $account = Account::factory()->forOffice(Office::query()->findOrFail($client->office_id))->create(['opening_date' => '2026-01-01']);
    $beta = $contract->milestones()->where('label', 'Beta')->sole();

    app(RecordPayment::class)->handle($owner, $beta->due, new PaymentInput(1_000_000, $account->id, PaymentMethod::Cash, '2026-03-20', null, null, null, false));

    expect($beta->due->refresh()->status)->toBe(DueStatus::Partial)->and($beta->due->amount_paid_cents)->toBe(1_000_000);
});

test('FR-MIL-04: rebalancing after a payment previews the change and leaves paid milestones alone', function () {
    CarbonImmutable::setTestNow('2026-03-20 09:00:00');
    ['contract' => $contract, 'client' => $client] = makeProject();
    $owner = User::factory()->owner()->create();
    $account = Account::factory()->forOffice(Office::query()->findOrFail($client->office_id))->create(['opening_date' => '2026-01-01']);
    [$deposit, $beta, $launch] = $contract->milestones()->with('due')->get()->all();

    // The deposit is paid in full, so it is locked at 40,000 DA and 60,000 DA remain to share.
    app(RecordPayment::class)->handle($owner, $deposit->due, new PaymentInput(4_000_000, $account->id, PaymentMethod::Cash, '2026-03-20', null, null, null, false));

    $rows = [
        ['id' => $beta->id, 'label' => 'Beta', 'mode' => 'percent', 'value' => 25],
        ['id' => $launch->id, 'label' => 'Launch', 'mode' => 'percent', 'value' => 75],
    ];

    $preview = $this->actingAs($owner)->postJson(route('contracts.milestones.preview', $contract), ['milestones' => $rows])->assertOk();

    expect($preview->json('plan'))->toBe([
        ['id' => $deposit->id, 'label' => 'Deposit', 'oldCents' => 4_000_000, 'newCents' => 4_000_000, 'locked' => true, 'canceled' => false],
        ['id' => $beta->id, 'label' => 'Beta', 'oldCents' => 3_000_000, 'newCents' => 1_500_000, 'locked' => false, 'canceled' => false],
        ['id' => $launch->id, 'label' => 'Launch', 'oldCents' => 3_000_000, 'newCents' => 4_500_000, 'locked' => false, 'canceled' => false],
    ]);

    // A preview writes nothing.
    expect($beta->refresh()->amount_cents)->toBe(3_000_000)->and($beta->due->refresh()->amount_due_cents)->toBe(3_000_000);

    $this->put(route('contracts.milestones.update', $contract), ['milestones' => $rows])->assertSessionHasNoErrors();

    expect($beta->refresh()->amount_cents)->toBe(1_500_000)
        ->and($beta->due->refresh()->amount_due_cents)->toBe(1_500_000)
        ->and($launch->refresh()->amount_cents)->toBe(4_500_000)
        ->and($deposit->refresh()->amount_cents)->toBe(4_000_000)
        ->and((int) $contract->milestones()->sum('amount_cents'))->toBe(10_000_000);
});

test('a rebalance must account for exactly the unpaid milestones and the amount left', function () {
    CarbonImmutable::setTestNow('2026-03-20 09:00:00');
    ['contract' => $contract, 'client' => $client] = makeProject();
    $owner = User::factory()->owner()->create();
    $account = Account::factory()->forOffice(Office::query()->findOrFail($client->office_id))->create(['opening_date' => '2026-01-01']);
    [$deposit, $beta, $launch] = $contract->milestones()->with('due')->get()->all();
    app(RecordPayment::class)->handle($owner, $deposit->due, new PaymentInput(1_000_000, $account->id, PaymentMethod::Cash, '2026-03-20', null, null, null, false));
    $this->actingAs($owner);

    // The deposit has a payment, so it cannot be part of the plan.
    $this->putJson(route('contracts.milestones.update', $contract), ['milestones' => [
        ['id' => $deposit->id, 'label' => 'Deposit', 'mode' => 'percent', 'value' => 50],
        ['id' => $beta->id, 'label' => 'Beta', 'mode' => 'percent', 'value' => 25],
        ['id' => $launch->id, 'label' => 'Launch', 'mode' => 'percent', 'value' => 25],
    ]])->assertJsonValidationErrors('milestones');

    // Percentages must total 100 of what is left to share.
    $this->putJson(route('contracts.milestones.update', $contract), ['milestones' => [
        ['id' => $beta->id, 'label' => 'Beta', 'mode' => 'percent', 'value' => 25],
        ['id' => $launch->id, 'label' => 'Launch', 'mode' => 'percent', 'value' => 25],
    ]])->assertJsonValidationErrors('milestones');

    // Amounts must equal what is left (60,000 DA).
    $this->putJson(route('contracts.milestones.update', $contract), ['milestones' => [
        ['id' => $beta->id, 'label' => 'Beta', 'mode' => 'amount', 'value' => 2_000_000],
        ['id' => $launch->id, 'label' => 'Launch', 'mode' => 'amount', 'value' => 3_000_000],
    ]])->assertJsonValidationErrors('milestones');

    expect($beta->refresh()->amount_cents)->toBe(3_000_000);
});
