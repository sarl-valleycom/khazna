<?php

use App\Enums\ContractStatus;
use App\Enums\Direction;
use App\Enums\DueKind;
use App\Enums\DueStatus;
use App\Models\Account;
use App\Models\Client;
use App\Models\Contract;
use App\Models\Due;
use App\Models\DueReminder;
use App\Models\Office;
use App\Models\Setting;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

afterEach(function () {
    CarbonImmutable::setTestNow();
});

/**
 * A client with an office, which dues and contracts require.
 *
 * @param  array<string, mixed>  $attributes
 */
function triageClient(array $attributes = []): Client
{
    return Client::factory()->forOffice(Office::factory()->create())->create($attributes);
}

/** Wednesday 11 March 2026, Algiers. The Saturday-to-Friday week ends on Friday 13 March. */
function triageNow(): void
{
    CarbonImmutable::setTestNow('2026-03-11 10:00:00');
}

function triageDue(Client $client, string $dueOn, int $cents = 100_000, array $overrides = []): Due
{
    return Due::factory()->forClient($client)->create([
        'period' => $dueOn,
        'due_on' => $dueOn,
        'amount_due_cents' => $cents,
        ...$overrides,
    ]);
}

/**
 * @return array<string, array{count: int, total: int}>
 */
function triageTabs($response): array
{
    $tabs = [];

    foreach ($response->viewData('page')['props']['tabs'] as $tab) {
        $tabs[$tab['value']] = [
            'count' => $tab['count'],
            'total' => $tab['collectCents'] === null ? null : $tab['collectCents'] + $tab['payCents'],
        ];
    }

    return $tabs;
}

test('guests are redirected from the home screen and the reminder endpoint', function () {
    $due = Due::factory()->create();

    $this->get(route('home'))->assertRedirect(route('login'));
    $this->post(route('dues.reminders.store', $due))->assertRedirect(route('login'));
});

test('the dashboard is its own page, separate from the triage home screen', function () {
    $this->actingAs(User::factory()->owner()->create())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('dashboard/Index'));
});

test('SRS scenario 3: a due dated yesterday appears under Overdue', function () {
    triageNow();
    $client = triageClient();
    $due = triageDue($client, '2026-03-10');

    $this->actingAs(User::factory()->owner()->create())
        ->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('triage/Index')
            ->where('filters.tab', 'overdue')
            ->has('dues.data', 1)
            ->where('dues.data.0.id', $due->id)
            ->where('dues.data.0.isOverdue', true));
});

test('SRS scenario 4: a due dated today at 00:30 Algiers is Today, not Overdue', function () {
    // 23:30 UTC on the 9th is 00:30 on the 10th in Algiers.
    CarbonImmutable::setTestNow('2026-03-09 23:30:00');
    $client = triageClient();
    triageDue($client, '2026-03-10');
    triageDue($client, '2026-03-09', 50_000);
    $owner = User::factory()->owner()->create();

    $tabs = triageTabs($this->actingAs($owner)->get(route('home')));

    expect($tabs['today'])->toBe(['count' => 1, 'total' => 100_000])
        ->and($tabs['overdue'])->toBe(['count' => 1, 'total' => 50_000]);

    $this->actingAs($owner)->get(route('home', ['tab' => 'today']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('dues.data', 1)
            ->where('dues.data.0.dueOn', '2026-03-10')
            ->where('dues.data.0.isOverdue', false));
});

test('the tabs are mutually exclusive and their counts and totals add up', function () {
    triageNow();
    $client = triageClient();
    triageDue($client, '2026-03-01', 100);          // overdue
    triageDue($client, '2026-03-10', 200);          // overdue
    triageDue($client, '2026-03-11', 400);          // today
    triageDue($client, '2026-03-12', 800);          // week
    triageDue($client, '2026-03-13', 1_600);        // week (last day, Friday)
    triageDue($client, '2026-03-14', 3_200);        // month (first day after the week)
    triageDue($client, '2026-03-31', 6_400);        // month
    triageDue($client, '2026-04-01', 12_800);       // beyond every tab
    triageDue($client, '2026-03-12', 100, ['status' => DueStatus::Paid, 'amount_paid_cents' => 100]);
    triageDue($client, '2026-03-12', 100, ['status' => DueStatus::Canceled, 'canceled_reason' => 'x']);
    triageDue($client, '2026-03-12', 1_000, ['status' => DueStatus::Partial, 'amount_paid_cents' => 400]); // 600 left

    $tabs = triageTabs($this->actingAs(User::factory()->owner()->create())->get(route('home')));

    expect($tabs['overdue'])->toBe(['count' => 2, 'total' => 300])
        ->and($tabs['today'])->toBe(['count' => 1, 'total' => 400])
        ->and($tabs['week'])->toBe(['count' => 3, 'total' => 800 + 1_600 + 600])
        ->and($tabs['month'])->toBe(['count' => 2, 'total' => 3_200 + 6_400]);
});

test('the week follows the configured start day', function () {
    triageNow();
    $client = triageClient();
    triageDue($client, '2026-03-15', 100); // Sunday
    $owner = User::factory()->owner()->create();

    expect(triageTabs($this->actingAs($owner)->get(route('home')))['month']['count'])->toBe(1);

    Setting::put(Setting::WEEK_START, 'monday');

    $tabs = triageTabs($this->actingAs($owner)->get(route('home')));
    expect($tabs['week']['count'])->toBe(1)->and($tabs['month']['count'])->toBe(0);
});

test('an unknown tab falls back to Overdue', function () {
    triageNow();

    $this->actingAs(User::factory()->owner()->create())
        ->get(route('home', ['tab' => 'nonsense']))
        ->assertInertia(fn (Assert $page) => $page->where('filters.tab', 'overdue'));
});

test('filters narrow both the rows and the tab counts', function () {
    triageNow();
    $office = Office::factory()->create();
    $otherOffice = Office::factory()->create();
    $managerA = User::factory()->accountManager()->forOffice($office->id)->create();
    $managerB = User::factory()->accountManager()->forOffice($otherOffice->id)->create();
    $clientA = Client::factory()->forManager($managerA)->forOffice($office)->create();
    $clientB = Client::factory()->forManager($managerB)->forOffice($otherOffice)->create();
    triageDue($clientA, '2026-03-01', 100, ['direction' => Direction::In, 'kind' => DueKind::Saas]);
    triageDue($clientA, '2026-03-02', 200, ['direction' => Direction::Out, 'kind' => DueKind::Expense]);
    triageDue($clientB, '2026-03-03', 400, ['direction' => Direction::In, 'kind' => DueKind::Project]);
    $owner = User::factory()->owner()->create();

    $count = fn (array $query) => triageTabs($this->actingAs($owner)->get(route('home', $query)))['overdue'];

    expect($count([]))->toBe(['count' => 3, 'total' => 700])
        ->and($count(['direction' => 'in']))->toBe(['count' => 2, 'total' => 500])
        ->and($count(['direction' => 'out']))->toBe(['count' => 1, 'total' => 200])
        ->and($count(['kind' => 'project']))->toBe(['count' => 1, 'total' => 400])
        ->and($count(['client' => $clientA->id]))->toBe(['count' => 2, 'total' => 300])
        ->and($count(['office' => $otherOffice->id]))->toBe(['count' => 1, 'total' => 400])
        ->and($count(['assignee' => $managerA->id]))->toBe(['count' => 2, 'total' => 300])
        ->and($count(['assignee' => $managerA->id, 'direction' => 'in']))->toBe(['count' => 1, 'total' => 100]);
});

test('FR-TRI-09: an account manager sees only their own clients', function () {
    triageNow();
    $office = Office::factory()->create();
    $mine = User::factory()->accountManager()->forOffice($office->id)->create();
    $theirs = User::factory()->accountManager()->forOffice($office->id)->create();
    $myClient = Client::factory()->forManager($mine)->forOffice($office)->create();
    $theirClient = Client::factory()->forManager($theirs)->forOffice($office)->create();
    $own = triageDue($myClient, '2026-03-01', 100);
    triageDue($theirClient, '2026-03-02', 900);

    $response = $this->actingAs($mine)->get(route('home'));

    expect(triageTabs($response)['overdue'])->toBe(['count' => 1, 'total' => 100]);

    $response->assertInertia(fn (Assert $page) => $page
        ->has('dues.data', 1)
        ->where('dues.data.0.id', $own->id)
        ->has('clients', 1)
        ->where('clients.0.id', $myClient->id)
        ->where('assignees', [])
        ->where('officeOptions', []));

    // Filtering by someone else's client or manager cannot widen the scope.
    $this->actingAs($mine)->get(route('home', ['client' => $theirClient->id]))
        ->assertInertia(fn (Assert $page) => $page->has('dues.data', 0));
    $this->actingAs($mine)->get(route('home', ['assignee' => $theirs->id]))
        ->assertInertia(fn (Assert $page) => $page->has('dues.data', 0));
});

test('SRS scenario 21: a user restricted to office 1 sees only office 1 and direct links are denied', function () {
    triageNow();
    $one = Office::factory()->create();
    $two = Office::factory()->create();
    $finance = User::factory()->finance()->forOffice($one->id)->create();
    $clientOne = Client::factory()->forOffice($one)->create();
    $clientTwo = Client::factory()->forOffice($two)->create();
    $dueOne = triageDue($clientOne, '2026-03-01', 100);
    $dueTwo = triageDue($clientTwo, '2026-03-01', 900);
    $accountTwo = Account::factory()->forOffice($two)->create();

    $this->actingAs($finance)->get(route('home'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('dues.data', 1)
            ->where('dues.data.0.id', $dueOne->id)
            ->where('officeOptions', [])
            ->has('paymentAccounts', 0));

    $this->get(route('accounts.show', $accountTwo))->assertForbidden();
    $this->get(route('clients.show', $clientTwo))->assertForbidden();
    $this->post(route('dues.reminders.store', $dueTwo))->assertForbidden();
    $this->patch(route('dues.postpone', $dueTwo), ['new_due_on' => '2026-04-01', 'reason' => 'x'])->assertForbidden();
});

test('Needs attention lists active contracts with no further due scheduled', function () {
    triageNow();
    $client = triageClient();
    $make = fn (array $attributes) => Contract::factory()->create([
        'client_id' => $client->id,
        'office_id' => $client->office_id,
        'status' => ContractStatus::Active,
        'cycle' => 'monthly',
        'starts_on' => '2025-06-15',
        'ends_on' => null,
        ...$attributes,
    ]);

    $ended = $make(['ends_on' => '2026-02-15']);                 // finished but still active: needs attention
    $oneTimeDone = $make(['cycle' => 'once', 'starts_on' => '2026-01-10']); // its only date has passed
    $yearly = $make(['cycle' => 'yearly', 'starts_on' => '2025-12-01']);   // next due is far away: fine
    $withDue = $make(['starts_on' => '2025-06-15']);
    triageDue($client, '2026-03-20', 100, ['contract_id' => $withDue->id]); // already has an upcoming due
    $make(['status' => ContractStatus::Paused, 'ends_on' => '2026-01-01']);  // paused: not flagged
    $running = $make(['starts_on' => '2025-06-15']);                           // monthly, endless: next date exists

    $owner = User::factory()->owner()->create();
    $tabs = triageTabs($this->actingAs($owner)->get(route('home')));

    expect($tabs['attention'])->toBe(['count' => 2, 'total' => null]);

    $this->actingAs($owner)->get(route('home', ['tab' => 'attention']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('attention', 2)
            ->where('attention.0.id', $ended->id)
            ->where('attention.1.id', $oneTimeDone->id)
            ->where('dues', null));

    expect(collect([$yearly->id, $running->id]))->not->toContain($ended->id);
});

test('rows are ordered by due date and paginated', function () {
    triageNow();
    $client = triageClient();

    foreach (range(1, 30) as $day) {
        triageDue($client, CarbonImmutable::parse('2026-02-01')->addDays($day - 1)->toDateString(), 100);
    }

    $this->actingAs(User::factory()->owner()->create())
        ->get(route('home'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('dues.data', 25)
            ->where('dues.total', 30)
            ->where('dues.data.0.dueOn', '2026-02-01')
            ->where('dues.data.24.dueOn', '2026-02-25'));
});

test('the triage page loads without lazy-loading or extra queries per row', function () {
    triageNow();
    $client = triageClient();
    $account = Account::factory()->forOffice($client->office)->create();

    foreach (range(1, 12) as $day) {
        triageDue($client, '2026-03-0'.min($day, 9));
    }

    Model::preventLazyLoading();
    DB::enableQueryLog();

    $this->actingAs(User::factory()->owner()->create())->get(route('home'))->assertOk();

    $queries = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($queries)->toBeLessThan(30)
        ->and($account->exists)->toBeTrue();
});

test('FR-REM: a WhatsApp reminder is built from the template and logged', function () {
    triageNow();
    $client = triageClient(['name' => 'SARL El Nour', 'phone' => '0550 12 34 56']);
    $due = triageDue($client, '2026-03-15', 1_000_000, ['status' => DueStatus::Partial, 'amount_paid_cents' => 250_000]);
    $owner = User::factory()->owner()->create();

    $response = $this->actingAs($owner)->postJson(route('dues.reminders.store', $due))->assertOk();

    $message = 'Hello SARL El Nour, this is a friendly reminder that 7,500.00 DA for '.$due->kind->label().' is due on 15 Mar 2026. Thank you.';
    $url = $response->json('url');

    expect($response->json('message'))->toBe($message)
        ->and($url)->toStartWith('https://wa.me/213550123456?text=')
        ->and(rawurldecode(substr($url, strpos($url, '?text=') + 6)))->toBe($message);

    $log = DueReminder::query()->sole();
    expect($log->due_id)->toBe($due->id)
        ->and($log->user_id)->toBe($owner->id)
        ->and($log->channel)->toBe('whatsapp')
        ->and($log->message)->toBe($message)
        ->and($log->phone)->toBe('213550123456')
        ->and($log->created_at)->not->toBeNull();
});

test('the reminder uses the owner-edited template and the contract name', function () {
    triageNow();
    Setting::put(Setting::REMINDER_TEMPLATE, 'Salam {client}: {amount} ({contract}) avant le {date}. {unknown}');
    $client = triageClient(['name' => 'Amine', 'phone' => '0661 23 45 67']);
    $contract = Contract::factory()->create(['client_id' => $client->id, 'office_id' => $client->office_id]);
    $due = triageDue($client, '2026-03-20', 500_000, ['contract_id' => $contract->id]);

    $this->actingAs(User::factory()->owner()->create())
        ->postJson(route('dues.reminders.store', $due))
        ->assertOk()
        ->assertJsonPath('message', 'Salam Amine: 5,000.00 DA (SaaS renewal) avant le 20 Mar 2026. {unknown}');
});

test('a reminder needs a valid phone and an open due the user can see', function () {
    triageNow();
    $office = Office::factory()->create();
    $manager = User::factory()->accountManager()->forOffice($office->id)->create();
    $stranger = User::factory()->accountManager()->forOffice($office->id)->create();
    $client = Client::factory()->forManager($manager)->forOffice($office)->create();
    $due = triageDue($client, '2026-03-15');

    $this->actingAs($stranger)->postJson(route('dues.reminders.store', $due))->assertForbidden();

    $due->update(['status' => DueStatus::Paid, 'amount_paid_cents' => $due->amount_due_cents]);
    $this->actingAs($manager)->postJson(route('dues.reminders.store', $due))->assertForbidden();

    $due->update(['status' => DueStatus::Pending, 'amount_paid_cents' => 0]);
    Illuminate\Support\Facades\DB::table('clients')->where('id', $client->id)->update(['phone' => 'not a phone']);

    $this->actingAs($manager)->postJson(route('dues.reminders.store', $due))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('phone');

    expect(DueReminder::query()->count())->toBe(0);
});

test('money to collect and money to pay are never added together', function () {
    triageNow();
    $client = triageClient();
    triageDue($client, '2026-03-01', 500_000, ['direction' => Direction::In]);
    triageDue($client, '2026-03-02', 300_000, ['direction' => Direction::Out, 'kind' => DueKind::Expense]);

    $response = $this->actingAs(User::factory()->owner()->create())->get(route('home'));
    $overdue = collect($response->viewData('page')['props']['tabs'])->firstWhere('value', 'overdue');

    expect($overdue['count'])->toBe(2)
        ->and($overdue['collectCents'])->toBe(500_000)
        ->and($overdue['payCents'])->toBe(300_000);
});

test('a reminder cannot be prepared for money we owe', function () {
    triageNow();
    $client = triageClient(['phone' => '0550 12 34 56']);
    $outgoing = triageDue($client, '2026-03-15', 100_000, ['direction' => Direction::Out, 'kind' => DueKind::Expense]);
    $owner = User::factory()->owner()->create();

    $this->actingAs($owner)->postJson(route('dues.reminders.store', $outgoing))->assertForbidden();

    $row = collect($this->get(route('home', ['tab' => 'month']))->viewData('page')['props']['dues']['data'])->firstWhere('id', $outgoing->id);

    expect($row['abilities']['remind'])->toBeFalse()
        ->and(DueReminder::query()->count())->toBe(0);
});
