<?php

use App\Actions\Payments\RecordPayment;
use App\Data\PaymentInput;
use App\Enums\AccountType;
use App\Enums\PaymentMethod;
use App\Enums\Role;
use App\Models\Account;
use App\Models\AccountEntry;
use App\Models\Activity;
use App\Models\Client;
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
function accountPayload(Office $office, array $overrides = []): array
{
    return [
        'office_id' => $office->id,
        'name' => 'Cash box',
        'type' => AccountType::Cash->value,
        'opening_balance_cents' => 250_000,
        'opening_date' => '2026-01-01',
        ...$overrides,
    ];
}

/**
 * Record a payment of $cents into $account on $date, going through the real action.
 */
function accountPay(Account $account, int $cents, string $date, string $direction = 'in'): void
{
    $client = Client::factory()->forOffice($account->office)->create();
    $due = Due::factory()->forClient($client)->create([
        'amount_due_cents' => $cents,
        'direction' => $direction,
    ]);

    app(RecordPayment::class)->handle(User::factory()->owner()->create(), $due, new PaymentInput(
        amountCents: $cents,
        accountId: $account->id,
        method: PaymentMethod::Cash,
        paidOn: $date,
        reference: null,
        notes: null,
        receipt: null,
        confirmOverpayment: false,
    ));
}

test('guests are redirected from the account pages', function () {
    $account = Account::factory()->create();

    $this->get(route('accounts.index'))->assertRedirect(route('login'));
    $this->get(route('accounts.show', $account))->assertRedirect(route('login'));
});

test('account managers never see balances or statements', function () {
    $account = Account::factory()->create();
    $manager = User::factory()->accountManager()->create();

    $this->actingAs($manager)->get(route('accounts.index'))->assertForbidden();
    $this->actingAs($manager)->get(route('accounts.show', $account))->assertForbidden();
    $this->actingAs($manager)->post(route('accounts.store'), accountPayload($account->office))->assertForbidden();
});

test('balances are grouped by office with computed totals', function () {
    CarbonImmutable::setTestNow('2026-03-10 12:00:00');
    $algiers = Office::factory()->create(['name' => 'Algiers HQ']);
    $oran = Office::factory()->create(['name' => 'Oran Office']);
    $cash = Account::factory()->forOffice($algiers)->create(['name' => 'Cash A', 'opening_balance_cents' => 1_000_000, 'opening_date' => '2026-01-01']);
    Account::factory()->forOffice($algiers)->create(['name' => 'CCP A', 'type' => AccountType::Ccp, 'opening_balance_cents' => 500_000, 'opening_date' => '2026-01-01']);
    Account::factory()->forOffice($oran)->create(['name' => 'Cash B', 'opening_balance_cents' => 200_000, 'opening_date' => '2026-01-01']);
    accountPay($cash, 300_000, '2026-03-01');

    $this->actingAs(User::factory()->owner()->create())
        ->get(route('accounts.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('accounts/Index')
            ->has('groups', 2)
            ->where('groups.0.officeName', 'Algiers HQ')
            ->where('groups.0.totalCents', 1_800_000)
            ->where('groups.0.accounts.0.balanceCents', 1_300_000)
            ->where('groups.0.accounts.0.openingLocked', true)
            ->where('groups.1.totalCents', 200_000)
            ->where('grandTotalCents', 2_000_000)
            ->where('canCreate', true));
});

test('finance restricted to an office sees and opens only that office', function () {
    $mine = Office::factory()->create();
    $theirs = Office::factory()->create();
    $myAccount = Account::factory()->forOffice($mine)->create();
    $theirAccount = Account::factory()->forOffice($theirs)->create();
    $finance = User::factory()->finance()->forOffice($mine->id)->create();

    $this->actingAs($finance)
        ->get(route('accounts.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('groups', 1)
            ->where('groups.0.accounts.0.id', $myAccount->id)
            ->where('canCreate', false));

    $this->actingAs($finance)->get(route('accounts.show', $myAccount))->assertOk();
    $this->actingAs($finance)->get(route('accounts.show', $theirAccount))->assertForbidden();
});

test('only the owner can create or edit an account', function () {
    $office = Office::factory()->create();
    $account = Account::factory()->forOffice($office)->create();
    $finance = User::factory()->finance()->create();

    $this->actingAs($finance)->post(route('accounts.store'), accountPayload($office))->assertForbidden();
    $this->actingAs($finance)
        ->patch(route('accounts.update', $account), collect(accountPayload($office))->except('office_id')->all())
        ->assertForbidden();

    $this->actingAs(User::factory()->owner()->create())
        ->post(route('accounts.store'), accountPayload($office))
        ->assertSessionHasNoErrors();

    $created = Account::query()->where('name', 'Cash box')->sole();
    expect($created->office_id)->toBe($office->id)
        ->and($created->type)->toBe(AccountType::Cash)
        ->and($created->currency)->toBe('DZD')
        ->and($created->opening_balance_cents)->toBe(250_000)
        ->and($created->active)->toBeTrue();
});

test('account creation is validated and names are unique per office', function () {
    $office = Office::factory()->create();
    $otherOffice = Office::factory()->create();
    Account::factory()->forOffice($office)->create(['name' => 'Cash box']);
    $owner = User::factory()->owner()->create();
    $this->actingAs($owner);

    $this->post(route('accounts.store'), accountPayload($office))->assertSessionHasErrors('name');
    $this->post(route('accounts.store'), accountPayload($otherOffice))->assertSessionHasNoErrors();
    $this->post(route('accounts.store'), accountPayload($office, ['name' => 'X', 'type' => 'wallet']))->assertSessionHasErrors('type');
    $this->post(route('accounts.store'), accountPayload($office, ['name' => 'Y', 'opening_balance_cents' => null]))->assertSessionHasErrors('opening_balance_cents');
    $this->post(route('accounts.store'), accountPayload($office, ['name' => 'Z', 'opening_date' => 'soon']))->assertSessionHasErrors('opening_date');
});

test('the opening balance and date lock once an account has entries', function () {
    CarbonImmutable::setTestNow('2026-03-10 12:00:00');
    $office = Office::factory()->create();
    $account = Account::factory()->forOffice($office)->create(['opening_balance_cents' => 100_000, 'opening_date' => '2026-01-01']);
    $this->actingAs(User::factory()->owner()->create());

    $this->patch(route('accounts.update', $account), accountPayload($office, [
        'office_id' => null,
        'name' => 'Renamed',
        'opening_balance_cents' => 999,
        'opening_date' => '2026-01-01',
    ]))->assertSessionHasErrors('office_id');

    $payload = fn (array $o = []) => collect(accountPayload($office, $o))->except('office_id')->all();

    $this->patch(route('accounts.update', $account), $payload(['name' => 'Renamed', 'opening_balance_cents' => 999, 'opening_date' => '2026-01-01']))
        ->assertSessionHasNoErrors();
    expect($account->refresh()->opening_balance_cents)->toBe(999)->and($account->name)->toBe('Renamed');

    accountPay($account, 10_000, '2026-03-01');

    $this->patch(route('accounts.update', $account), $payload(['name' => 'Renamed', 'opening_balance_cents' => 1_000, 'opening_date' => '2026-01-01']))
        ->assertSessionHasErrors('opening_balance_cents');
    $this->patch(route('accounts.update', $account), $payload(['name' => 'Renamed again', 'opening_balance_cents' => 999, 'opening_date' => '2026-01-01']))
        ->assertSessionHasNoErrors();
    // It still holds money, so it cannot be switched off (FR-ACC-12).
    $this->patch(route('accounts.update', $account), $payload(['name' => 'Renamed again', 'opening_balance_cents' => 999, 'opening_date' => '2026-01-01', 'active' => false]))
        ->assertSessionHasErrors('active');

    $account->refresh();
    expect($account->opening_balance_cents)->toBe(999)
        ->and($account->name)->toBe('Renamed again')
        ->and($account->active)->toBeTrue();
});

test('account changes are audited', function () {
    $office = Office::factory()->create();
    $this->actingAs(User::factory()->owner()->create())
        ->post(route('accounts.store'), accountPayload($office));

    expect(Activity::query()->where('subject_type', Account::class)->where('event', 'created')->count())->toBe(1);
});

test('the statement shows in, out and a running balance with a date filter', function () {
    CarbonImmutable::setTestNow('2026-03-20 12:00:00');
    $office = Office::factory()->create();
    $account = Account::factory()->forOffice($office)->create(['opening_balance_cents' => 100_000, 'opening_date' => '2026-01-01']);
    accountPay($account, 50_000, '2026-02-01');
    accountPay($account, 20_000, '2026-03-05', 'out');
    accountPay($account, 30_000, '2026-03-15');
    $owner = User::factory()->owner()->create();

    $this->actingAs($owner)
        ->get(route('accounts.show', $account))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('accounts/Show')
            ->where('summary.startCents', 100_000)
            ->where('summary.endCents', 160_000)
            ->has('entries.data', 3)
            ->where('entries.data.0.runningCents', 150_000)
            ->where('entries.data.1.runningCents', 130_000)
            ->where('entries.data.1.outCents', 20_000)
            ->where('entries.data.2.runningCents', 160_000)
            ->where('account.balanceCents', 160_000));

    $this->actingAs($owner)
        ->get(route('accounts.show', [$account, 'from' => '2026-03-01', 'to' => '2026-03-31']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('summary.startCents', 150_000)
            ->where('summary.endCents', 160_000)
            ->has('entries.data', 2)
            ->where('entries.data.0.runningCents', 130_000)
            ->where('entries.data.1.runningCents', 160_000));

    $this->actingAs($owner)
        ->get(route('accounts.show', [$account, 'from' => '2026-03-31', 'to' => '2026-03-01']))
        ->assertSessionHasErrors('to');
});

test('account balances come from entries only and agree with the statement', function () {
    CarbonImmutable::setTestNow('2026-03-20 12:00:00');
    $account = Account::factory()->create(['opening_balance_cents' => 0, 'opening_date' => '2026-01-01']);
    accountPay($account, 70_000, '2026-02-10');
    accountPay($account, 5_000, '2026-02-11', 'out');

    $net = AccountEntry::query()->where('account_id', $account->id)->get()->sum(fn (AccountEntry $e): int => $e->signedCents());

    expect($account->balanceCents())->toBe(65_000)->toBe($net)
        ->and(Account::query()->withBalance()->whereKey($account->id)->sole()->balance_cents)->toBe(65_000);
});

test('roles reach the account pages as the role matrix says', function (Role $role, int $status) {
    $account = Account::factory()->create();
    $user = User::factory()->create(['role' => $role]);

    $this->actingAs($user)->get(route('accounts.index'))->assertStatus($status);
    $this->actingAs($user)->get(route('accounts.show', $account))->assertStatus($status);
})->with([
    'owner' => [Role::Owner, 200],
    'finance' => [Role::Finance, 200],
    'account manager' => [Role::AccountManager, 403],
]);
