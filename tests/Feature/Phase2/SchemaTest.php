<?php

use App\Enums\CategoryKind;
use App\Enums\Direction;
use App\Models\Category;
use App\Models\Client;
use App\Models\Contract;
use App\Models\Due;
use App\Models\Employee;
use App\Models\EmployeeSalary;
use App\Models\Office;
use App\Models\Supplier;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

afterEach(function () {
    CarbonImmutable::setTestNow();
});

test('the categories every installation needs are seeded', function () {
    $costs = Category::query()->where('kind', CategoryKind::Cost)->pluck('name')->all();

    expect($costs)->toContain('Hosting', 'Domains', 'Tools', 'Rent', 'Salaries', 'Taxes', 'Bank fees', 'Other', 'Reconciliation difference')
        ->and(Category::named(Category::BANK_FEES)->kind)->toBe(CategoryKind::Cost);
});

test('the salary in force on a date is the latest raise on or before it', function () {
    $employee = Employee::factory()->create();
    EmployeeSalary::query()->create(['employee_id' => $employee->id, 'amount_cents' => 6_000_000, 'effective_from' => '2025-01-01']);
    EmployeeSalary::query()->create(['employee_id' => $employee->id, 'amount_cents' => 7_000_000, 'effective_from' => '2025-07-15']);

    expect($employee->salaryOn('2024-12-31'))->toBeNull()
        ->and($employee->salaryOn('2025-01-01'))->toBe(6_000_000)
        ->and($employee->salaryOn('2025-07-14'))->toBe(6_000_000)
        ->and($employee->salaryOn('2025-07-15'))->toBe(7_000_000)
        ->and($employee->salaryOn(CarbonImmutable::parse('2026-03-31')))->toBe(7_000_000);
});

test('an employee carries no salary attribute, so none can leak through serialisation', function () {
    $employee = Employee::factory()->earning(6_000_000)->create();

    $keys = array_keys($employee->fresh()->toArray());

    expect($keys)->not->toContain('salary', 'base_salary', 'amount_cents')
        ->and(json_encode($employee->fresh()->toArray()))->not->toContain('6000000');
});

test('a salary can only be due once per employee and month', function () {
    $employee = Employee::factory()->create();
    Due::factory()->forEmployee($employee, '2026-03-10')->create(['amount_due_cents' => 100]);

    expect(fn () => Due::factory()->forEmployee($employee, '2026-03-20')->create(['amount_due_cents' => 100]))
        ->toThrow(QueryException::class);

    // A different month is fine.
    Due::factory()->forEmployee($employee, '2026-04-10')->create(['amount_due_cents' => 100]);

    expect(Due::query()->where('employee_id', $employee->id)->count())->toBe(2);
});

test('dues and contracts can belong to a supplier or an employee instead of a client', function () {
    $office = Office::factory()->create();
    $supplier = Supplier::factory()->create(['name' => 'Hosting SARL']);
    $employee = Employee::factory()->forOffice($office)->create(['name' => 'Karim']);

    $supplierDue = Due::factory()->forSupplier($supplier, $office)->create();
    $salaryDue = Due::factory()->forEmployee($employee, '2026-03-01')->create();
    $clientDue = Due::factory()->forClient(Client::factory()->forOffice($office)->create(['name' => 'Alpha']))->create();

    $loaded = Due::query()->withParties()->orderBy('id')->get();

    expect($loaded->map->partyName()->all())->toBe(['Hosting SARL', 'Karim', 'Alpha'])
        ->and($loaded->map->partyType()->all())->toBe(['supplier', 'employee', 'client'])
        ->and($supplierDue->direction)->toBe(Direction::Out);

    $contract = Contract::factory()->create([
        'client_id' => null,
        'supplier_id' => $supplier->id,
        'office_id' => $office->id,
        'direction' => Direction::Out,
        'type' => 'recurring_cost',
    ]);

    expect($contract->fresh()->supplier->name)->toBe('Hosting SARL');
});

test('a milestone waiting for delivery has no date, is never overdue and sits in no time-based tab', function () {
    CarbonImmutable::setTestNow('2026-03-11 10:00:00');
    $client = Client::factory()->forOffice(Office::factory()->create())->create();
    $waiting = Due::factory()->forClient($client)->waitingForDelivery()->create(['amount_due_cents' => 900_000]);
    $owner = User::factory()->owner()->create();

    expect($waiting->isWaitingForDelivery())->toBeTrue()
        ->and($waiting->isOverdue())->toBeFalse();

    $response = $this->actingAs($owner)->get(route('home'));
    $tabs = collect($response->viewData('page')['props']['tabs'])->keyBy('value');

    expect($tabs->only(['overdue', 'today', 'week', 'month'])->pluck('count')->sum())->toBe(0);

    // The client page still lists it, labelled as waiting.
    $this->actingAs($owner)->get(route('clients.show', $client))
        ->assertInertia(fn (Assert $page) => $page
            ->has('openDues', 1)
            ->where('openDues.0.dueOn', null)
            ->where('openDues.0.isOverdue', false));
});

test('salary dues are visible to people with the salary permission and to nobody else', function () {
    $office = Office::factory()->create();
    $employee = Employee::factory()->forOffice($office)->create();
    $due = Due::factory()->forEmployee($employee, '2026-03-01')->create(['amount_due_cents' => 5_500_000]);

    $owner = User::factory()->owner()->create();
    $finance = User::factory()->finance()->create();
    $manager = User::factory()->accountManager()->forOffice($office->id)->create();

    expect(Due::query()->visibleTo($owner)->count())->toBe(1)
        ->and(Due::query()->visibleTo($finance)->count())->toBe(0)
        ->and(Due::query()->visibleTo($manager)->count())->toBe(0)
        ->and($owner->can('view', $due))->toBeTrue()
        ->and($finance->can('view', $due))->toBeFalse()
        ->and($finance->can('pay', $due))->toBeFalse()
        ->and($manager->can('view', $due))->toBeFalse();

    // Granting finance the salary permission (an owner decision) opens them up.
    config()->set('khazna.finance_can_view_salaries', true);
    expect(Due::query()->visibleTo($finance)->count())->toBe(1)
        ->and($finance->can('view', $due))->toBeTrue();
});

test('a due that is only open money out never appears for account managers', function () {
    $office = Office::factory()->create();
    $manager = User::factory()->accountManager()->forOffice($office->id)->create();
    Due::factory()->forSupplier(Supplier::factory()->create(), $office)->create();

    expect(Due::query()->visibleTo($manager)->count())->toBe(0);
});
