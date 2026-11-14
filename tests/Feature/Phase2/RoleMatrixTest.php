<?php

use App\Models\Account;
use App\Models\Category;
use App\Models\Client;
use App\Models\Contract;
use App\Models\Due;
use App\Models\Employee;
use App\Models\ManualEntry;
use App\Models\Office;
use App\Models\Reconciliation;
use App\Models\Supplier;
use App\Models\Transfer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;

uses(RefreshDatabase::class);

/**
 * Every page added in phase 2, and who may open it. A row is
 * [route name, owner, finance, finance with the salary permission, account manager]
 * with the HTTP status each should get.
 */
dataset('phase 2 pages', [
    'dashboard' => ['dashboard', 200, 200, 200, 200],
    'forecast' => ['forecast', 200, 200, 200, 403],
    'cash report' => ['reports.cash', 200, 200, 200, 403],
    'employees' => ['employees.index', 200, 200, 200, 403],
    'suppliers' => ['suppliers.index', 200, 200, 200, 403],
    'categories' => ['categories.index', 200, 200, 200, 403],
    'transfers' => ['transfers.index', 200, 200, 200, 403],
    'manual entries' => ['entries.index', 200, 200, 200, 403],
    'reconcile' => ['reconciliations.index', 200, 200, 200, 403],
    'payroll adjustments' => ['payroll.adjustments', 200, 403, 200, 403],
    'month-close' => ['payroll.close', 200, 403, 200, 403],
]);

test('each phase 2 page opens for exactly the roles it is meant for', function (string $route, int $owner, int $finance, int $financeWithSalaries, int $manager) {
    $office = Office::factory()->create();
    Account::factory()->forOffice($office)->create();

    $users = [
        [User::factory()->owner()->create(), $owner, false],
        [User::factory()->finance()->create(), $finance, false],
        [User::factory()->finance()->create(), $financeWithSalaries, true],
        [User::factory()->accountManager()->forOffice($office->id)->create(), $manager, false],
    ];

    $this->get(route($route))->assertRedirect(route('login'));

    foreach ($users as [$user, $expected, $salaryPermission]) {
        config()->set('khazna.finance_can_view_salaries', $salaryPermission);
        $this->actingAs($user)->get(route($route))->assertStatus($expected);
    }
})->with('phase 2 pages');

test('account managers cannot send anything to the money-out and payroll routes', function () {
    $office = Office::factory()->create();
    $manager = User::factory()->accountManager()->forOffice($office->id)->create();
    $employee = Employee::factory()->forOffice($office)->create();
    $supplier = Supplier::factory()->create();
    $account = Account::factory()->forOffice($office)->create();
    $category = Category::named('Rent');
    $this->actingAs($manager);

    $forbidden = [
        ['post', route('suppliers.store'), ['name' => 'X']],
        ['patch', route('suppliers.update', $supplier), ['name' => 'X']],
        ['delete', route('suppliers.destroy', $supplier), []],
        ['post', route('categories.store'), ['name' => 'X', 'kind' => 'cost']],
        ['post', route('employees.store'), ['name' => 'X', 'office_id' => $office->id]],
        ['patch', route('employees.update', $employee), ['name' => 'X', 'office_id' => $office->id]],
        ['post', route('employees.salaries.store', $employee), ['amount_cents' => 1, 'effective_from' => '2026-01-01']],
        ['post', route('payroll.close.store'), ['month' => '2026-03']],
        ['post', route('payroll.adjustments.store'), ['employee_id' => $employee->id, 'type' => 'bonus', 'month' => '2026-03', 'amount_cents' => 1, 'reason' => 'x']],
        ['post', route('transfers.store'), ['from_account_id' => $account->id, 'to_account_id' => Account::factory()->forOffice($office)->create()->id, 'amount_cents' => 1, 'transfer_date' => '2026-03-01']],
        ['post', route('entries.store'), ['account_id' => $account->id, 'direction' => 'out', 'amount_cents' => 1, 'entry_date' => '2026-03-01', 'category_id' => $category->id, 'description' => 'x']],
        ['post', route('reconciliations.store'), ['account_id' => $account->id, 'as_of' => '2026-03-01', 'counted_balance_cents' => 0]],
    ];

    foreach ($forbidden as [$method, $url, $payload]) {
        $this->{$method}($url, $payload)->assertForbidden();
    }
});

test('role x resource x action policy matrix for the phase 2 resources', function () {
    $mine = Office::factory()->create();
    $theirs = Office::factory()->create();
    $actors = [
        'owner' => User::factory()->owner()->create(),
        'finance' => User::factory()->finance()->create(),
        'financeMine' => User::factory()->finance()->forOffice($mine->id)->create(),
        'financeTheirs' => User::factory()->finance()->forOffice($theirs->id)->create(),
        'manager' => User::factory()->accountManager()->forOffice($mine->id)->create(),
    ];

    $employee = Employee::factory()->forOffice($mine)->create();
    $supplier = Supplier::factory()->create();
    $category = Category::named('Rent');
    $a = Account::factory()->forOffice($mine)->create();
    $b = Account::factory()->forOffice($mine)->create();
    $far = Account::factory()->forOffice($theirs)->create();
    $local = Transfer::query()->create(['from_account_id' => $a->id, 'to_account_id' => $b->id, 'amount_cents' => 1, 'transfer_date' => '2026-03-01']);
    $crossOffice = Transfer::query()->create(['from_account_id' => $a->id, 'to_account_id' => $far->id, 'amount_cents' => 1, 'transfer_date' => '2026-03-01']);
    $reversed = Transfer::query()->create(['from_account_id' => $a->id, 'to_account_id' => $b->id, 'amount_cents' => 1, 'transfer_date' => '2026-03-01', 'reversed_at' => now()]);
    $entry = ManualEntry::query()->create(['account_id' => $a->id, 'direction' => 'out', 'amount_cents' => 1, 'entry_date' => '2026-03-01', 'category_id' => $category->id, 'description' => 'x']);
    $reconciliation = Reconciliation::query()->create(['account_id' => $a->id, 'as_of' => '2026-03-01', 'counted_balance_cents' => 0, 'system_balance_cents' => 0, 'difference_cents' => 0]);

    // [ability, subject, owner, finance, financeMine, financeTheirs, manager]
    $matrix = [
        ['viewAny', Employee::class, 1, 1, 1, 1, 0],
        ['create', Employee::class, 1, 1, 1, 1, 0],
        ['view', $employee, 1, 1, 1, 0, 0],
        ['update', $employee, 1, 1, 1, 0, 0],
        ['setSalary', $employee, 1, 0, 0, 0, 0],

        ['viewAny', Supplier::class, 1, 1, 1, 1, 0],
        ['create', Supplier::class, 1, 1, 1, 1, 0],
        ['update', $supplier, 1, 1, 1, 1, 0],
        ['delete', $supplier, 1, 1, 1, 1, 0],

        ['viewAny', Category::class, 1, 1, 1, 1, 0],
        ['create', Category::class, 1, 1, 1, 1, 0],
        ['update', $category, 1, 1, 1, 1, 0],

        ['viewAny', Transfer::class, 1, 1, 1, 1, 0],
        ['create', Transfer::class, 1, 1, 1, 1, 0],
        ['view', $local, 1, 1, 1, 0, 0],
        ['reverse', $local, 1, 1, 1, 0, 0],
        ['view', $crossOffice, 1, 1, 0, 0, 0],          // a restricted user sees only transfers wholly in their office
        ['reverse', $crossOffice, 1, 1, 0, 0, 0],
        ['reverse', $reversed, 0, 0, 0, 0, 0],

        ['viewAny', ManualEntry::class, 1, 1, 1, 1, 0],
        ['create', ManualEntry::class, 1, 1, 1, 1, 0],
        ['view', $entry, 1, 1, 1, 0, 0],
        ['reverse', $entry, 1, 1, 1, 0, 0],

        ['viewAny', Reconciliation::class, 1, 1, 1, 1, 0],
        ['create', Reconciliation::class, 1, 1, 1, 1, 0],
        ['adjust', $reconciliation, 1, 1, 1, 0, 0],
    ];

    $failures = [];
    $names = array_keys($actors);

    foreach ($matrix as $row) {
        [$ability, $subject] = $row;

        foreach ($names as $index => $name) {
            $expected = (bool) $row[$index + 2];

            if (Gate::forUser($actors[$name])->allows($ability, $subject) !== $expected) {
                $label = is_string($subject) ? class_basename($subject) : class_basename($subject).'#'.$subject->getKey();
                $failures[] = sprintf('%s %s on %s: expected %s', $name, $ability, $label, $expected ? 'allow' : 'deny');
            }
        }
    }

    expect($failures)->toBe([]);
});

test('an account manager sees none of the phase 2 money-out data on any page they can open', function () {
    $office = Office::factory()->create();
    $manager = User::factory()->accountManager()->forOffice($office->id)->create();
    $client = Client::factory()->forManager($manager)->forOffice($office)->create();
    Contract::factory()->create(['client_id' => $client->id, 'office_id' => $office->id]);
    Due::factory()->forClient($client)->create(['due_on' => '2026-03-01', 'period' => '2026-03-01']);
    Due::factory()->forSupplier(Supplier::factory()->create(['name' => 'SUPPLIERMARKER']), $office)->create(['due_on' => '2026-03-01', 'period' => '2026-03-01']);
    $employee = Employee::factory()->forOffice($office)->earning(9_999_999)->create(['name' => 'EMPLOYEEMARKER']);
    Due::factory()->forEmployee($employee, '2026-02-01')->create();
    $this->actingAs($manager);

    foreach ([route('home'), route('dashboard'), route('clients.index'), route('clients.show', $client), route('contracts.index')] as $url) {
        $content = $this->get($url)->assertOk()->getContent();

        expect($content)->not->toContain('SUPPLIERMARKER', $url)
            ->and($content)->not->toContain('EMPLOYEEMARKER', $url)
            ->and($content)->not->toContain('9999999', $url);
    }

    // Their dashboard is their own clients only, with no balances and no salary card.
    $this->get(route('dashboard'))->assertInertia(fn ($page) => $page
        ->missing('salary')
        ->loadDeferredProps(fn ($reloaded) => $reloaded->where('balances', [])->missing('salary')));
});
