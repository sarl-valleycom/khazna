<?php

use App\Models\Account;
use App\Models\Client;
use App\Models\Due;
use App\Models\Employee;
use App\Models\Office;
use App\Models\Reconciliation;
use App\Models\Supplier;
use App\Models\User;
use App\Queries\ForecastQuery;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

afterEach(function () {
    CarbonImmutable::setTestNow();
});

/** Wednesday 11 March 2026: week 1 covers 11-17 March, week 2 18-24 March. */
function forecastNow(): CarbonImmutable
{
    CarbonImmutable::setTestNow('2026-03-11 10:00:00');

    return CarbonImmutable::parse('2026-03-11');
}

function forecastAccount(Office $office, int $balance): Account
{
    return Account::factory()->forOffice($office)->create(['opening_balance_cents' => $balance, 'opening_date' => '2026-01-01']);
}

function inDue(Office $office, string $dueOn, int $cents, array $overrides = []): Due
{
    return Due::factory()->forClient(Client::factory()->forOffice($office)->create())->create([
        'due_on' => $dueOn,
        'period' => $dueOn,
        'amount_due_cents' => $cents,
        ...$overrides,
    ]);
}

function outDue(Office $office, string $dueOn, int $cents, array $overrides = []): Due
{
    return Due::factory()->forSupplier(Supplier::factory()->create(), $office)->create([
        'due_on' => $dueOn,
        'period' => $dueOn,
        'amount_due_cents' => $cents,
        ...$overrides,
    ]);
}

function salaryDue(Employee $employee, string $dueOn, int $cents): Due
{
    return Due::factory()->forEmployee($employee, '2026-02-01')->create(['due_on' => $dueOn, 'amount_due_cents' => $cents]);
}

/** @return array<string, mixed> */
function forecastFor(User $user, int $days = 30, bool $includeOverdue = false): array
{
    return (new ForecastQuery)->handle($user, $days, $includeOverdue);
}

test('it starts from the real balance of the accounts, bucketed by week with a running balance', function () {
    forecastNow();
    $office = Office::factory()->create();
    forecastAccount($office, 10_000_000);
    inDue($office, '2026-03-12', 2_000_000);                    // week 1
    inDue($office, '2026-03-20', 1_500_000);                    // week 2
    outDue($office, '2026-03-13', 800_000);                     // week 1
    outDue($office, '2026-03-26', 3_000_000);                   // week 3
    $owner = User::factory()->owner()->create();

    $forecast = forecastFor($owner);
    $company = $forecast['company'];

    expect($forecast['days'])->toBe(30)
        ->and($company['startBalanceCents'])->toBe(10_000_000)
        ->and(collect($company['buckets'])->pluck('label')->all())->toBe(['W1', 'W2', 'W3', 'W4', 'W5'])
        ->and(collect($company['buckets'])->pluck('inCents')->all())->toBe([2_000_000, 1_500_000, 0, 0, 0])
        ->and(collect($company['buckets'])->pluck('outCents')->all())->toBe([800_000, 0, 3_000_000, 0, 0])
        ->and(collect($company['buckets'])->pluck('balanceCents')->all())->toBe([11_200_000, 12_700_000, 9_700_000, 9_700_000, 9_700_000])
        ->and($company['endBalanceCents'])->toBe(9_700_000);
});

test('the view is 30, 60 or 90 days and anything else becomes 30', function () {
    forecastNow();
    $owner = User::factory()->owner()->create();

    expect(count(forecastFor($owner, 30)['company']['buckets']))->toBe(5)
        ->and(count(forecastFor($owner, 60)['company']['buckets']))->toBe(9)
        ->and(count(forecastFor($owner, 90)['company']['buckets']))->toBe(13)
        ->and(forecastFor($owner, 45)['days'])->toBe(30);
});

test('dues use their remaining amount on their due date and ignore settled, canceled and undated ones', function () {
    forecastNow();
    $office = Office::factory()->create();
    forecastAccount($office, 0);
    inDue($office, '2026-03-12', 1_000_000, ['status' => 'partial', 'amount_paid_cents' => 400_000]); // 6,000 left
    inDue($office, '2026-03-12', 900_000, ['status' => 'paid', 'amount_paid_cents' => 900_000]);
    inDue($office, '2026-03-12', 900_000, ['status' => 'canceled']);
    inDue($office, '2026-03-12', 700_000)->update(['due_on' => null, 'period' => null]);   // waiting for delivery
    inDue($office, '2026-06-30', 500_000);                                                  // beyond the window

    $week = forecastFor(User::factory()->owner()->create())['company']['buckets'][0];

    expect($week['inCents'])->toBe(600_000);
});

test('overdue money out counts on day zero, overdue money in is listed apart and counted only on request', function () {
    forecastNow();
    $office = Office::factory()->create();
    forecastAccount($office, 5_000_000);
    outDue($office, '2026-02-25', 1_200_000);     // overdue payable: certain to leave
    inDue($office, '2026-02-20', 3_000_000);      // overdue receivable: might never come
    $owner = User::factory()->owner()->create();

    $default = forecastFor($owner)['company'];
    expect($default['buckets'][0]['outCents'])->toBe(1_200_000)
        ->and($default['buckets'][0]['inCents'])->toBe(0)
        ->and($default['overdueIn']['count'])->toBe(1)
        ->and($default['overdueIn']['cents'])->toBe(3_000_000)
        ->and($default['overdueIn']['dues'][0]['overdue'])->toBeTrue()
        ->and($default['endBalanceCents'])->toBe(3_800_000);

    $optimistic = forecastFor($owner, 30, includeOverdue: true)['company'];
    expect($optimistic['buckets'][0]['inCents'])->toBe(3_000_000)
        ->and($optimistic['endBalanceCents'])->toBe(6_800_000)
        // Still listed apart either way.
        ->and($optimistic['overdueIn']['cents'])->toBe(3_000_000);
});

test('SRS scenario 13: 200,000 in and 350,000 out over 30 days from a 100,000 opening goes negative', function () {
    forecastNow();
    $office = Office::factory()->create();
    forecastAccount($office, 10_000_000);
    inDue($office, '2026-03-14', 20_000_000);
    outDue($office, '2026-03-20', 20_000_000);
    outDue($office, '2026-03-28', 15_000_000);

    $company = forecastFor(User::factory()->owner()->create())['company'];

    expect($company['endBalanceCents'])->toBe(-5_000_000)
        ->and($company['lowestBalanceCents'])->toBe(-5_000_000)
        ->and($company['firstNegativeBucket'])->toBe('W3')
        ->and(collect($company['buckets'])->pluck('negative')->all())->toBe([false, false, true, true, true]);
});

test('SRS scenario 24: one office can be short while the company total looks healthy', function () {
    forecastNow();
    $one = Office::factory()->create(['name' => 'Office 1']);
    $two = Office::factory()->create(['name' => 'Office 2']);
    forecastAccount($one, 8_000_000);                                   // 80,000
    forecastAccount($two, 50_000_000);                                  // 500,000
    $team = Employee::factory()->forOffice($one)->earning(12_000_000)->create();
    salaryDue($team, '2026-03-15', 12_000_000);                         // office 1 owes 120,000 on the 15th
    $owner = User::factory()->owner()->create();

    $forecast = forecastFor($owner);
    $office1 = collect($forecast['offices'])->firstWhere('label', 'Office 1');
    $office2 = collect($forecast['offices'])->firstWhere('label', 'Office 2');

    expect($forecast['company']['endBalanceCents'])->toBe(46_000_000)        // looks fine overall
        ->and($forecast['company']['firstNegativeBucket'])->toBeNull()
        ->and($office1['endBalanceCents'])->toBe(-4_000_000)                // but office 1 goes negative
        ->and($office1['firstNegativeBucket'])->toBe('W1')
        ->and($office2['endBalanceCents'])->toBe(50_000_000)
        ->and($office2['firstNegativeBucket'])->toBeNull();

    // Filtered to office 1 the page shows exactly its own accounts and dues.
    expect($office1['startBalanceCents'])->toBe(8_000_000);
});

test('weeks where the balance falls below the next salary run are highlighted', function () {
    forecastNow();
    $office = Office::factory()->create();
    forecastAccount($office, 12_000_000);
    $a = Employee::factory()->forOffice($office)->earning(5_000_000)->create();
    $b = Employee::factory()->forOffice($office)->earning(4_000_000)->create();
    salaryDue($a, '2026-03-28', 5_000_000);
    salaryDue($b, '2026-03-28', 4_000_000);                              // the next salary run is 90,000 together
    outDue($office, '2026-03-12', 5_000_000);                            // week 1: 120,000 - 50,000 = 70,000, under the run
    inDue($office, '2026-03-20', 6_000_000);                             // week 2: back to 130,000, safe
    // week 3 pays the 90,000 run: 40,000 left

    $company = forecastFor(User::factory()->owner()->create())['company'];

    expect($company['nextPayrollCents'])->toBe(9_000_000)
        ->and(collect($company['buckets'])->pluck('belowPayroll')->all())->toBe([true, false, true, true, true]);
});

test('finance gets a forecast without payroll, the owner gets it with', function () {
    forecastNow();
    $office = Office::factory()->create();
    forecastAccount($office, 9_000_000);
    $employee = Employee::factory()->forOffice($office)->earning(5_000_000)->create();
    salaryDue($employee, '2026-03-28', 5_000_000);
    outDue($office, '2026-03-20', 1_000_000);

    $owner = forecastFor(User::factory()->owner()->create());
    $finance = forecastFor(User::factory()->finance()->create());

    expect($owner['includesPayroll'])->toBeTrue()
        ->and($owner['company']['endBalanceCents'])->toBe(3_000_000)
        ->and($finance['includesPayroll'])->toBeFalse()
        ->and($finance['company']['endBalanceCents'])->toBe(8_000_000)
        ->and($finance['company']['nextPayrollCents'])->toBeNull()
        ->and(json_encode($finance))->not->toContain('Salary')
        ->and(json_encode($finance))->not->toContain('5000000');
});

test('accounts not reconciled for over 30 days, or never, are flagged', function () {
    forecastNow();
    $office = Office::factory()->create();
    $recent = forecastAccount($office, 0);
    $old = forecastAccount($office, 0);
    $never = forecastAccount($office, 0);
    Reconciliation::query()->create(['account_id' => $recent->id, 'as_of' => '2026-03-05', 'counted_balance_cents' => 0, 'system_balance_cents' => 0, 'difference_cents' => 0]);
    Reconciliation::query()->create(['account_id' => $old->id, 'as_of' => '2026-02-01', 'counted_balance_cents' => 0, 'system_balance_cents' => 0, 'difference_cents' => 0]);

    $stale = collect(forecastFor(User::factory()->owner()->create())['company']['staleAccounts'])->keyBy('id');

    expect($stale->keys()->sort()->values()->all())->toBe(collect([$old->id, $never->id])->sort()->values()->all())
        ->and($stale[$old->id]['lastReconciled'])->toBe('2026-02-01')
        ->and($stale[$never->id]['lastReconciled'])->toBeNull();
});

test('a user restricted to an office only forecasts that office', function () {
    forecastNow();
    $mine = Office::factory()->create(['name' => 'Mine']);
    $theirs = Office::factory()->create(['name' => 'Theirs']);
    forecastAccount($mine, 1_000_000);
    forecastAccount($theirs, 9_000_000);
    inDue($theirs, '2026-03-14', 7_000_000);
    $finance = User::factory()->finance()->forOffice($mine->id)->create();

    $forecast = forecastFor($finance);

    expect($forecast['company']['label'])->toBe('Mine')
        ->and($forecast['company']['startBalanceCents'])->toBe(1_000_000)
        ->and($forecast['company']['endBalanceCents'])->toBe(1_000_000)
        ->and(collect($forecast['offices'])->pluck('label')->all())->toBe(['Mine']);
});

test('clicking a bucket lists the dues it contains', function () {
    forecastNow();
    $office = Office::factory()->create();
    forecastAccount($office, 0);
    inDue($office, '2026-03-12', 1_000_000);
    outDue($office, '2026-03-13', 400_000);
    outDue($office, '2026-03-30', 100_000);

    $week1 = forecastFor(User::factory()->owner()->create())['company']['buckets'][0];

    expect($week1['dues'])->toHaveCount(2)
        ->and(collect($week1['dues'])->pluck('direction')->sort()->values()->all())->toBe(['in', 'out'])
        ->and($week1['dues'][0])->toHaveKeys(['id', 'partyName', 'kindLabel', 'dueOn', 'remainingCents', 'overdue']);
});

test('the forecast page is for owner and finance only', function () {
    forecastNow();
    $office = Office::factory()->create();
    forecastAccount($office, 1_000_000);
    $this->get(route('forecast'))->assertRedirect(route('login'));

    $this->actingAs(User::factory()->accountManager()->create())->get(route('forecast'))->assertForbidden();

    foreach ([User::factory()->owner(), User::factory()->finance()] as $factory) {
        $this->actingAs($factory->create())->get(route('forecast', ['days' => 60, 'include_overdue' => 1]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('forecast/Index')
                ->where('days', 60)
                ->where('includeOverdueIn', true)
                ->has('company.buckets', 9));
    }
});
