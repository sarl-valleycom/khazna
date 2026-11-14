<?php

use App\Actions\Payments\RecordPayment;
use App\Data\PaymentInput;
use App\Enums\PaymentMethod;
use App\Models\Account;
use App\Models\Client;
use App\Models\Contract;
use App\Models\Due;
use App\Models\Office;
use App\Models\Payment;
use App\Models\Setting;
use App\Models\User;
use App\Support\AlgerianDate;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

afterEach(function () {
    CarbonImmutable::setTestNow();
});

/**
 * Two offices, three account managers and one record of every kind for each.
 * Names carry markers so a leak is a plain string search over the response.
 *
 * @return array<string, mixed>
 */
function hardeningWorld(): array
{
    CarbonImmutable::setTestNow('2026-03-11 10:00:00');
    Storage::fake('local');

    $one = Office::factory()->create(['name' => 'OFFICEONE']);
    $two = Office::factory()->create(['name' => 'OFFICETWO']);
    $world = ['one' => $one, 'two' => $two];

    $world['owner'] = User::factory()->owner()->create();
    $world['finance'] = User::factory()->finance()->create();
    $world['financeOne'] = User::factory()->finance()->forOffice($one->id)->create();
    $world['financeTwo'] = User::factory()->finance()->forOffice($two->id)->create();
    $world['amMine'] = User::factory()->accountManager()->forOffice($one->id)->create();
    $world['amPeer'] = User::factory()->accountManager()->forOffice($one->id)->create();
    $world['amOther'] = User::factory()->accountManager()->forOffice($two->id)->create();

    $kinds = [
        'mine' => ['amMine', $one, 'MARKERMINE'],
        'peer' => ['amPeer', $one, 'MARKERPEER'],
        'other' => ['amOther', $two, 'MARKEROTHEROFFICE'],
    ];

    foreach ($kinds as $key => [$manager, $office, $marker]) {
        $account = Account::factory()->forOffice($office)->create(['name' => 'ACC'.strtoupper($key), 'opening_balance_cents' => 1_000_000]);
        $client = Client::factory()->forManager($world[$manager])->forOffice($office)->create(['name' => $marker]);
        $contract = Contract::factory()->create(['client_id' => $client->id, 'office_id' => $office->id, 'status' => 'active']);
        $due = Due::factory()->forClient($client)->create([
            'contract_id' => $contract->id,
            'due_on' => '2026-03-01',
            'period' => '2026-03-01',
            'amount_due_cents' => 500_000,
        ]);
        $payment = app(RecordPayment::class)->handle($world['owner'], $due, new PaymentInput(
            amountCents: 100_000,
            accountId: $account->id,
            method: PaymentMethod::Cash,
            paidOn: AlgerianDate::todayString(),
            reference: null,
            notes: null,
            receipt: UploadedFile::fake()->create('r.pdf', 10, 'application/pdf'),
            confirmOverpayment: false,
        ));

        $world[$key] = compact('account', 'client', 'contract', 'due', 'payment');
    }

    return $world;
}

/** @return list<string> Every list-like route a user can open. */
function hardeningListUrls(array $world): array
{
    $tabs = ['overdue', 'today', 'week', 'month', 'attention'];

    return [
        route('clients.index'),
        route('contracts.index'),
        ...array_map(fn (string $tab): string => route('home', ['tab' => $tab]), $tabs),
        route('home', ['client' => $world['peer']['client']->id]),
        route('home', ['client' => $world['other']['client']->id]),
        route('home', ['office' => $world['two']->id]),
        route('home', ['assignee' => $world['amPeer']->id]),
        route('contracts.index', ['client' => $world['peer']['client']->id]),
        route('clients.index', ['q' => 'MARKER']),
    ];
}

test('an account manager never sees a foreign client, contract, due, payment or account on any list', function () {
    $world = hardeningWorld();
    $this->actingAs($world['amMine']);

    foreach (hardeningListUrls($world) as $url) {
        $content = $this->get($url)->assertOk()->getContent();

        expect($content)->not->toContain('MARKERPEER', $url)
            ->and($content)->not->toContain('MARKEROTHEROFFICE', $url)
            ->and($content)->not->toContain('ACCOTHER', $url);
    }

    // Their own records are there.
    expect($this->get(route('clients.index'))->getContent())->toContain('MARKERMINE')
        ->and($this->get(route('home', ['tab' => 'overdue']))->getContent())->toContain('MARKERMINE');

    // The pages of their own records do not drag in anyone else's.
    foreach ([route('clients.show', $world['mine']['client']), route('contracts.show', $world['mine']['contract'])] as $url) {
        $content = $this->get($url)->assertOk()->getContent();

        expect($content)->toContain('MARKERMINE')
            ->and($content)->not->toContain('MARKERPEER')
            ->and($content)->not->toContain('MARKEROTHEROFFICE');
    }

    // Balances and statements are not theirs to see.
    $this->get(route('accounts.index'))->assertForbidden();
});

test('an account manager is denied every direct link to someone else\'s records', function (string $who) {
    $world = hardeningWorld();
    $foreign = $world[$who];
    $this->actingAs($world['amMine']);

    $this->get(route('clients.show', $foreign['client']))->assertForbidden();
    $this->patch(route('clients.update', $foreign['client']), [
        'name' => 'X',
        'phone' => '0550123456',
        'account_manager_id' => $world['amMine']->id,
    ])->assertForbidden();
    $this->delete(route('clients.destroy', $foreign['client']))->assertForbidden();
    $this->get(route('contracts.show', $foreign['contract']))->assertForbidden();
    $this->get(route('contracts.attachment', $foreign['contract']))->assertForbidden();
    $this->patch(route('contracts.status', $foreign['contract']), ['status' => 'paused', 'reason' => 'x'])->assertForbidden();
    $this->post(route('dues.store', $foreign['client']), ['direction' => 'in', 'kind' => 'other', 'due_on' => '2026-04-01', 'amount_cents' => 100])->assertForbidden();
    $this->patch(route('dues.postpone', $foreign['due']), ['new_due_on' => '2026-04-01', 'reason' => 'x'])->assertForbidden();
    $this->patch(route('dues.cancel', $foreign['due']), ['reason' => 'x'])->assertForbidden();
    $this->post(route('payments.store', $foreign['due']), ['amount_cents' => 100, 'account_id' => $foreign['account']->id, 'method' => 'cash', 'paid_on' => '2026-03-11'])->assertForbidden();
    $this->postJson(route('dues.reminders.store', $foreign['due']))->assertForbidden();
    $this->post(route('payments.reverse', $foreign['payment']), ['reason' => 'x'])->assertForbidden();
    $this->get(route('payments.receipt', $foreign['payment']))->assertForbidden();
    $this->get(route('accounts.show', $foreign['account']))->assertForbidden();
})->with(['a peer in the same office' => ['peer'], 'a manager in the other office' => ['other']]);

test('SRS 21: a user restricted to office 1 sees only office 1 and every link to office 2 is denied', function () {
    $world = hardeningWorld();
    $other = $world['other'];
    $this->actingAs($world['financeOne']);

    foreach ([...hardeningListUrls($world), route('accounts.index')] as $url) {
        $content = $this->get($url)->assertOk()->getContent();

        expect($content)->not->toContain('MARKEROTHEROFFICE', $url)
            ->and($content)->not->toContain('ACCOTHER', $url);
    }

    // Everything in their own office is visible, including peers' clients (they are finance, not an account manager).
    $content = $this->get(route('clients.index'))->getContent();
    expect($content)->toContain('MARKERMINE')->and($content)->toContain('MARKERPEER');

    $this->get(route('clients.show', $other['client']))->assertForbidden();
    $this->get(route('contracts.show', $other['contract']))->assertForbidden();
    $this->get(route('contracts.attachment', $other['contract']))->assertForbidden();
    $this->patch(route('dues.postpone', $other['due']), ['new_due_on' => '2026-04-01', 'reason' => 'x'])->assertForbidden();
    $this->patch(route('dues.cancel', $other['due']), ['reason' => 'x'])->assertForbidden();
    $this->post(route('payments.store', $other['due']), ['amount_cents' => 100, 'account_id' => $other['account']->id, 'method' => 'cash', 'paid_on' => '2026-03-11'])->assertForbidden();
    $this->postJson(route('dues.reminders.store', $other['due']))->assertForbidden();
    $this->post(route('payments.reverse', $other['payment']), ['reason' => 'x'])->assertForbidden();
    $this->get(route('payments.receipt', $other['payment']))->assertForbidden();
    $this->get(route('accounts.show', $other['account']))->assertForbidden();
    $this->patch(route('accounts.update', $other['account']), [
        'name' => 'X',
        'type' => 'cash',
        'opening_balance_cents' => 0,
        'opening_date' => '2026-01-01',
    ])->assertForbidden();

    // And they cannot pay into an account of the other office for an office 1 due.
    $mine = $world['mine'];
    $this->post(route('payments.store', $mine['due']), ['amount_cents' => 100, 'account_id' => $other['account']->id, 'method' => 'cash', 'paid_on' => '2026-03-11'])
        ->assertSessionHasErrors('account_id');
});

test('role x resource x action policy matrix', function () {
    $w = hardeningWorld();
    $mine = $w['mine'];
    $actors = [
        'owner' => $w['owner'],
        'finance' => $w['finance'],
        'financeOne' => $w['financeOne'],
        'financeTwo' => $w['financeTwo'],
        'amMine' => $w['amMine'],
        'amPeer' => $w['amPeer'],
    ];

    $reversed = Payment::factory()->create([
        'due_id' => $mine['due']->id,
        'account_id' => $mine['account']->id,
        'reversed_at' => now(),
    ]);
    $settled = Due::factory()->forClient($mine['client'])->create(['status' => 'paid', 'amount_paid_cents' => 100_000, 'amount_due_cents' => 100_000]);

    // [ability, subject, owner, finance, financeOne, financeTwo, amMine, amPeer]
    $matrix = [
        ['viewAny', Client::class, 1, 1, 1, 1, 1, 1],
        ['create', Client::class, 1, 1, 1, 1, 1, 1],
        ['import', Client::class, 1, 1, 1, 1, 0, 0],
        ['view', $mine['client'], 1, 1, 1, 0, 1, 0],
        ['update', $mine['client'], 1, 1, 1, 0, 1, 0],
        ['delete', $mine['client'], 1, 1, 1, 0, 1, 0],

        ['viewAny', Contract::class, 1, 1, 1, 1, 1, 1],
        ['create', Contract::class, 1, 1, 1, 1, 1, 1],
        ['view', $mine['contract'], 1, 1, 1, 0, 1, 0],
        ['update', $mine['contract'], 1, 1, 1, 0, 1, 0],
        ['changeStatus', $mine['contract'], 1, 1, 1, 0, 1, 0],

        ['viewAny', Due::class, 1, 1, 1, 1, 1, 1],
        ['create', Due::class, 1, 1, 1, 1, 1, 1],
        ['view', $mine['due'], 1, 1, 1, 0, 1, 0],
        ['pay', $mine['due'], 1, 1, 1, 0, 1, 0],
        ['postpone', $mine['due'], 1, 1, 1, 0, 1, 0],
        ['cancel', $mine['due'], 1, 1, 1, 0, 1, 0],
        ['remind', $mine['due'], 1, 1, 1, 0, 1, 0],
        ['view', $settled, 1, 1, 1, 0, 1, 0],
        ['pay', $settled, 0, 0, 0, 0, 0, 0],
        ['postpone', $settled, 0, 0, 0, 0, 0, 0],
        ['cancel', $settled, 0, 0, 0, 0, 0, 0],
        ['remind', $settled, 0, 0, 0, 0, 0, 0],

        ['view', $mine['payment'], 1, 1, 1, 0, 1, 0],
        ['reverse', $mine['payment'], 1, 1, 1, 0, 0, 0],
        ['reverse', $reversed, 0, 0, 0, 0, 0, 0],

        ['viewAny', Account::class, 1, 1, 1, 1, 0, 0],
        ['create', Account::class, 1, 0, 0, 0, 0, 0],
        ['view', $mine['account'], 1, 1, 1, 0, 0, 0],
        ['update', $mine['account'], 1, 0, 0, 0, 0, 0],

        ['viewAny', User::class, 1, 0, 0, 0, 0, 0],
        ['create', User::class, 1, 0, 0, 0, 0, 0],
        ['update', $w['amMine'], 1, 0, 0, 0, 0, 0],
        ['resetPassword', $w['amMine'], 1, 0, 0, 0, 0, 0],

        ['viewAny', Office::class, 1, 0, 0, 0, 0, 0],
        ['create', Office::class, 1, 0, 0, 0, 0, 0],
        ['update', $w['one'], 1, 0, 0, 0, 0, 0],

        ['viewAny', Setting::class, 1, 0, 0, 0, 0, 0],
        ['update', Setting::class, 1, 0, 0, 0, 0, 0],
    ];

    $failures = [];
    $names = array_keys($actors);

    foreach ($matrix as $row) {
        [$ability, $subject] = $row;

        foreach ($names as $index => $name) {
            $expected = (bool) $row[$index + 2];
            $actual = Gate::forUser($actors[$name])->allows($ability, $subject);

            if ($actual !== $expected) {
                $label = is_string($subject) ? class_basename($subject) : class_basename($subject).'#'.$subject->getKey();
                $failures[] = sprintf('%s %s on %s: expected %s', $name, $ability, $label, $expected ? 'allow' : 'deny');
            }
        }
    }

    expect($failures)->toBe([]);
});

/**
 * @param  callable(): void  $request
 */
function hardeningQueryCount(callable $request): int
{
    DB::flushQueryLog();
    DB::enableQueryLog();
    $request();
    $count = count(DB::getQueryLog());
    DB::disableQueryLog();

    return $count;
}

test('triage, the client page and the contract page do not add queries as rows grow', function () {
    $world = hardeningWorld();
    $owner = $world['owner'];
    $mine = $world['mine'];

    $sequence = 0;
    $addRows = function (int $howMany) use ($mine, $owner, &$sequence): void {
        foreach (range(1, $howMany) as $ignored) {
            $sequence++;
            $period = CarbonImmutable::parse('2024-01-01')->addDays($sequence)->toDateString();
            $due = Due::factory()->forClient($mine['client'])->create([
                'contract_id' => $mine['contract']->id,
                'due_on' => CarbonImmutable::parse('2026-02-01')->addDays($sequence % 20)->toDateString(),
                'period' => $period,
                'amount_due_cents' => 300_000,
            ]);

            app(RecordPayment::class)->handle($owner, $due, new PaymentInput(
                amountCents: 100_000,
                accountId: $mine['account']->id,
                method: PaymentMethod::Cash,
                paidOn: AlgerianDate::todayString(),
                reference: null,
                notes: null,
                receipt: null,
                confirmOverpayment: false,
            ));
        }
    };

    $urls = [
        'triage' => route('home', ['tab' => 'overdue']),
        'client page' => route('clients.show', $mine['client']),
        'contract page' => route('contracts.show', $mine['contract']),
        'accounts' => route('accounts.index'),
        'statement' => route('accounts.show', $mine['account']),
    ];

    $this->actingAs($owner);
    $addRows(3);

    // One warm-up pass, so per-request caches (settings) are not counted as growth.
    foreach ($urls as $url) {
        $this->get($url)->assertOk();
    }

    $before = collect($urls)->map(fn (string $url): int => hardeningQueryCount(fn () => $this->get($url)->assertOk()));

    $addRows(12);
    $after = collect($urls)->map(fn (string $url): int => hardeningQueryCount(fn () => $this->get($url)->assertOk()));

    // Lazy loading is also forbidden in tests, so any per-row relation access would already have thrown.
    expect($after->all())->toBe($before->all());
});
