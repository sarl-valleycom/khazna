<?php

use App\Actions\Payments\RecordPayment;
use App\Actions\Payments\ReversePayment;
use App\Data\PaymentInput;
use App\Enums\ContractStatus;
use App\Enums\Direction;
use App\Enums\DueStatus;
use App\Enums\PaymentMethod;
use App\Models\Account;
use App\Models\Client;
use App\Models\Contract;
use App\Models\Due;
use App\Models\Employee;
use App\Models\EmployeeSalary;
use App\Models\Office;
use App\Models\Supplier;
use App\Models\User;
use App\Support\Metrics\DashboardMetrics;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

afterEach(function () {
    CarbonImmutable::setTestNow();
});

/** Wednesday 11 March 2026. */
function metricsNow(): void
{
    CarbonImmutable::setTestNow('2026-03-11 10:00:00');
}

function metricsContract(Office $office, string $cycle, int $cents, array $overrides = []): Contract
{
    return Contract::factory()->create([
        'client_id' => Client::factory()->forOffice($office)->create()->id,
        'office_id' => $office->id,
        'cycle' => $cycle,
        'amount_cents' => $cents,
        'starts_on' => '2026-01-01',
        'ends_on' => null,
        'status' => ContractStatus::Active,
        ...$overrides,
    ]);
}

function metricsDue(Client $client, ?string $dueOn, int $cents, array $overrides = []): Due
{
    return Due::factory()->forClient($client)->create([
        'due_on' => $dueOn,
        'period' => $dueOn,
        'amount_due_cents' => $cents,
        ...$overrides,
    ]);
}

test('MRR normalises monthly, quarterly and yearly contracts: 10,000 + 30,000/3 + 120,000/12 = 30,000 DA', function () {
    metricsNow();
    $office = Office::factory()->create();
    metricsContract($office, 'monthly', 1_000_000);
    metricsContract($office, 'quarterly', 3_000_000);
    metricsContract($office, 'yearly', 12_000_000);
    $owner = User::factory()->owner()->create();

    expect((new DashboardMetrics($owner))->mrr())->toBe(3_000_000);
});

test('MRR leaves out what is not live money in: paused, ended, not yet started, one-time and costs', function () {
    metricsNow();
    $office = Office::factory()->create();
    metricsContract($office, 'monthly', 1_000_000);
    metricsContract($office, 'monthly', 9_000_000, ['status' => ContractStatus::Paused]);
    metricsContract($office, 'monthly', 9_000_000, ['status' => ContractStatus::Canceled]);
    metricsContract($office, 'monthly', 9_000_000, ['ends_on' => '2026-02-28']);
    metricsContract($office, 'monthly', 9_000_000, ['starts_on' => '2026-04-01']);
    metricsContract($office, 'once', 9_000_000);
    metricsContract($office, 'monthly', 9_000_000, ['direction' => Direction::Out]);
    // Ends later this year: still live today.
    metricsContract($office, 'monthly', 500_000, ['ends_on' => '2026-12-31']);

    expect((new DashboardMetrics(User::factory()->owner()->create()))->mrr())->toBe(1_500_000);
});

test('MRR rounds each contract to the nearest centime and loses none in a thirds example', function () {
    metricsNow();
    $office = Office::factory()->create();
    // 1,000.00 DA a quarter is 333.33 a month.
    metricsContract($office, 'quarterly', 100_000);

    expect((new DashboardMetrics(User::factory()->owner()->create()))->mrr())->toBe(33_333);
});

test('outstanding and overdue are hand-computed from open money-in dues only', function () {
    metricsNow();
    $office = Office::factory()->create();
    $client = Client::factory()->forOffice($office)->create();
    metricsDue($client, '2026-03-01', 1_000_000);                                                     // overdue, 10,000
    metricsDue($client, '2026-03-05', 800_000, ['status' => DueStatus::Partial, 'amount_paid_cents' => 300_000]); // overdue, 5,000 left
    metricsDue($client, '2026-03-11', 200_000);                                                       // today: due, not overdue
    metricsDue($client, '2026-04-20', 4_000_000);                                                     // future
    metricsDue($client, '2026-03-02', 600_000, ['status' => DueStatus::Paid, 'amount_paid_cents' => 600_000]);
    metricsDue($client, '2026-03-02', 700_000, ['status' => DueStatus::Canceled]);
    metricsDue($client, null, 900_000);                                                               // waiting for delivery: owed, but never late
    Due::factory()->forSupplier(Supplier::factory()->create(), $office)->create(['due_on' => '2026-03-01', 'period' => '2026-03-01', 'amount_due_cents' => 5_000_000]); // we owe: not a receivable

    $metrics = new DashboardMetrics(User::factory()->owner()->create());

    expect($metrics->outstanding())->toBe(1_000_000 + 500_000 + 200_000 + 4_000_000 + 900_000)
        ->and($metrics->overdue())->toBe(1_000_000 + 500_000);
});

test('collected this month counts non-reversed payments on money-in dues dated in the month', function () {
    metricsNow();
    $office = Office::factory()->create();
    $account = Account::factory()->forOffice($office)->create(['opening_date' => '2026-01-01']);
    $client = Client::factory()->forOffice($office)->create();
    $owner = User::factory()->owner()->create();
    $pay = fn (Due $due, int $cents, string $on) => app(RecordPayment::class)->handle($owner, $due, new PaymentInput($cents, $account->id, PaymentMethod::Cash, $on, null, null, null, false));

    $pay(metricsDue($client, '2026-03-01', 1_000_000), 1_000_000, '2026-03-02');
    $pay(metricsDue($client, '2026-03-03', 400_000), 250_000, '2026-03-10');
    $pay(metricsDue($client, '2026-02-01', 700_000), 700_000, '2026-02-27');     // last month
    $reversed = $pay(metricsDue($client, '2026-03-04', 900_000), 900_000, '2026-03-04');
    app(ReversePayment::class)->handle($owner, $reversed, 'Bounced');
    $cost = Due::factory()->forSupplier(Supplier::factory()->create(), $office)->create(['due_on' => '2026-03-05', 'period' => '2026-03-05', 'amount_due_cents' => 500_000]);
    $pay($cost, 500_000, '2026-03-05');                                            // money out is not collected

    expect((new DashboardMetrics($owner))->collectedThisMonth())->toBe(1_250_000);
});

test('upcoming renewals are the contracts with money due in the next thirty days', function () {
    metricsNow();
    $office = Office::factory()->create();
    $client = Client::factory()->forOffice($office)->create();
    $a = metricsContract($office, 'monthly', 1_000_000);
    $b = metricsContract($office, 'monthly', 2_000_000);
    $far = metricsContract($office, 'monthly', 9_000_000);
    // Two dues on contract a count once; contract b is just inside the window; far is outside.
    foreach (['2026-03-15', '2026-04-05'] as $date) {
        metricsDue($client, $date, 1_000_000, ['contract_id' => $a->id]);
    }
    metricsDue($client, '2026-04-10', 2_000_000, ['contract_id' => $b->id]);       // exactly 30 days ahead
    metricsDue($client, '2026-04-11', 9_000_000, ['contract_id' => $far->id]);     // 31 days
    metricsDue($client, '2026-03-12', 300_000);                                    // a manual due is no renewal

    $renewals = (new DashboardMetrics(User::factory()->owner()->create()))->upcomingRenewals();

    expect($renewals)->toBe(['count' => 2, 'cents' => 1_000_000 + 1_000_000 + 2_000_000]);
});

test('the salary bill is the salaries in force for active employees, and only for people who may see salaries', function () {
    metricsNow();
    $office = Office::factory()->create();
    Employee::factory()->forOffice($office)->earning(6_000_000, '2025-01-01')->create();
    $raised = Employee::factory()->forOffice($office)->earning(4_000_000, '2025-01-01')->create();
    EmployeeSalary::query()->create(['employee_id' => $raised->id, 'amount_cents' => 5_000_000, 'effective_from' => '2026-03-01']);
    EmployeeSalary::query()->create(['employee_id' => $raised->id, 'amount_cents' => 9_000_000, 'effective_from' => '2026-06-01']); // not yet
    Employee::factory()->forOffice($office)->inactive()->earning(7_000_000)->create();

    expect((new DashboardMetrics(User::factory()->owner()->create()))->salaryBill())->toBe(11_000_000)
        ->and((new DashboardMetrics(User::factory()->finance()->create()))->salaryBill())->toBeNull()
        ->and((new DashboardMetrics(User::factory()->accountManager()->create()))->salaryBill())->toBeNull();
});

test('every card narrows to one office', function () {
    metricsNow();
    $algiers = Office::factory()->create();
    $oran = Office::factory()->create();
    metricsContract($algiers, 'monthly', 1_000_000);
    metricsContract($oran, 'monthly', 4_000_000);
    metricsDue(Client::factory()->forOffice($algiers)->create(), '2026-03-01', 700_000);
    metricsDue(Client::factory()->forOffice($oran)->create(), '2026-03-01', 3_000_000);
    Employee::factory()->forOffice($algiers)->earning(5_000_000)->create();
    Employee::factory()->forOffice($oran)->earning(8_000_000)->create();
    Account::factory()->forOffice($algiers)->create(['opening_balance_cents' => 111]);
    Account::factory()->forOffice($oran)->create(['opening_balance_cents' => 222]);
    $owner = User::factory()->owner()->create();

    $all = new DashboardMetrics($owner);
    $oneOffice = new DashboardMetrics($owner, $oran->id);

    expect($all->mrr())->toBe(5_000_000)->and($oneOffice->mrr())->toBe(4_000_000)
        ->and($all->outstanding())->toBe(3_700_000)->and($oneOffice->outstanding())->toBe(3_000_000)
        ->and($all->salaryBill())->toBe(13_000_000)->and($oneOffice->salaryBill())->toBe(8_000_000)
        ->and(collect($all->accountBalances())->pluck('totalCents')->all())->toBe([111, 222])
        ->and(collect($oneOffice->accountBalances())->pluck('officeId')->all())->toBe([$oran->id]);
});

test('a user restricted to an office only ever sees that office, and account managers only their own clients', function () {
    metricsNow();
    $algiers = Office::factory()->create();
    $oran = Office::factory()->create();
    $manager = User::factory()->accountManager()->forOffice($algiers->id)->create();
    $myClient = Client::factory()->forManager($manager)->forOffice($algiers)->create();
    metricsContract($algiers, 'monthly', 1_000_000, ['client_id' => $myClient->id]);
    metricsContract($algiers, 'monthly', 2_000_000);                          // someone else's client, same office
    metricsContract($oran, 'monthly', 4_000_000);
    metricsDue($myClient, '2026-03-01', 700_000);
    metricsDue(Client::factory()->forOffice($oran)->create(), '2026-03-01', 3_000_000);

    $mine = new DashboardMetrics($manager);
    $finance = new DashboardMetrics(User::factory()->finance()->forOffice($algiers->id)->create(), $oran->id);

    expect($mine->mrr())->toBe(1_000_000)->and($mine->outstanding())->toBe(700_000)
        ->and($mine->accountBalances())->toBe([])
        // Asking a restricted user for another office gets nothing: the visibility rule wins.
        ->and($finance->outstanding())->toBe(0)->and($finance->mrr())->toBe(0);
});

test('the dashboard page defers its cards and builds the salary card only for people who may see it', function () {
    metricsNow();
    $office = Office::factory()->create();
    Employee::factory()->forOffice($office)->earning(6_000_000)->create();
    metricsContract($office, 'monthly', 1_000_000);

    $this->actingAs(User::factory()->owner()->create())->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
        ->component('dashboard/Index')
        ->missing('kpis')
        ->missing('balances')
        ->missing('salary')
        ->loadDeferredProps(fn (Assert $reloaded) => $reloaded
            ->where('kpis.mrrCents', 1_000_000)
            ->where('salary.billCents', 6_000_000)
            ->has('balances')));

    $this->actingAs(User::factory()->finance()->create())->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
        ->component('dashboard/Index')
        ->loadDeferredProps(fn (Assert $reloaded) => $reloaded
            ->where('kpis.mrrCents', 1_000_000)
            ->missing('salary')));

    $content = $this->get(route('dashboard'))->getContent();
    expect($content)->not->toContain('6000000');
});

test('the dashboard is open to every role and held to the signed-in office', function () {
    metricsNow();
    $office = Office::factory()->create();
    $other = Office::factory()->create();
    metricsContract($office, 'monthly', 1_000_000);
    metricsContract($other, 'monthly', 7_000_000);
    $restricted = User::factory()->finance()->forOffice($office->id)->create();

    $this->actingAs($restricted)->get(route('dashboard', ['office' => $other->id]))->assertInertia(fn (Assert $page) => $page
        ->where('officeId', $office->id)
        ->loadDeferredProps(fn (Assert $reloaded) => $reloaded->where('kpis.mrrCents', 1_000_000)));

    foreach ([User::factory()->owner(), User::factory()->finance(), User::factory()->accountManager()->forOffice($office->id)] as $factory) {
        $this->actingAs($factory->create())->get(route('dashboard'))->assertOk();
    }

    $this->post(route('logout'));
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});
