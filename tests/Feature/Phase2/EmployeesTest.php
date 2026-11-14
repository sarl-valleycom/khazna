<?php

use App\Enums\EmployeeStatus;
use App\Enums\Role;
use App\Models\Account;
use App\Models\Activity;
use App\Models\Client;
use App\Models\Contract;
use App\Models\Due;
use App\Models\Employee;
use App\Models\EmployeeSalary;
use App\Models\Office;
use App\Models\Supplier;
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
function employeePayload(Office $office, array $overrides = []): array
{
    return [
        'name' => 'Karim Benali',
        'phone' => '0550 12 34 56',
        'title' => 'Developer',
        'hire_date' => '2025-01-10',
        'office_id' => $office->id,
        ...$overrides,
    ];
}

test('guests are redirected and account managers are refused', function () {
    $employee = Employee::factory()->create();
    $manager = User::factory()->accountManager()->create();

    $this->get(route('employees.index'))->assertRedirect(route('login'));
    $this->get(route('employees.show', $employee))->assertRedirect(route('login'));

    $this->actingAs($manager);
    $this->get(route('employees.index'))->assertForbidden();
    $this->get(route('employees.show', $employee))->assertForbidden();
    $this->post(route('employees.store'), employeePayload($employee->office))->assertForbidden();
    $this->post(route('employees.salaries.store', $employee), ['amount_cents' => 100, 'effective_from' => '2026-01-01'])->assertForbidden();
});

test('the owner creates an employee with a starting salary that lands in the history table', function () {
    $office = Office::factory()->create();
    $owner = $this->actingAsRole(Role::Owner);

    $this->post(route('employees.store'), employeePayload($office, ['salary_cents' => 6_000_000, 'salary_from' => '2025-01-10']))
        ->assertSessionHasNoErrors();

    $employee = Employee::query()->sole();
    $row = EmployeeSalary::query()->sole();

    expect($employee->name)->toBe('Karim Benali')
        ->and($employee->office_id)->toBe($office->id)
        ->and($row->employee_id)->toBe($employee->id)
        ->and($row->amount_cents)->toBe(6_000_000)
        ->and($row->effective_from->toDateString())->toBe('2025-01-10')
        ->and($row->created_by)->toBe($owner->id);
});

test('finance manages people but cannot send or set a salary', function () {
    $office = Office::factory()->create();
    $this->actingAsRole(Role::Finance);

    $this->post(route('employees.store'), employeePayload($office, ['salary_cents' => 6_000_000, 'salary_from' => '2025-01-10']))
        ->assertSessionHasErrors(['salary_cents', 'salary_from']);
    expect(Employee::query()->count())->toBe(0);

    $this->post(route('employees.store'), employeePayload($office))->assertSessionHasNoErrors();
    $employee = Employee::query()->sole();

    $this->patch(route('employees.update', $employee), employeePayload($office, ['title' => 'Lead', 'status' => 'inactive']))
        ->assertSessionHasNoErrors();
    expect($employee->refresh()->title)->toBe('Lead')->and($employee->status)->toBe(EmployeeStatus::Inactive);

    $this->post(route('employees.salaries.store', $employee), ['amount_cents' => 100, 'effective_from' => '2026-01-01'])->assertForbidden();
    expect(EmployeeSalary::query()->count())->toBe(0);
});

test('editing a person never touches their salary and a raise only adds a row', function () {
    $office = Office::factory()->create();
    $owner = $this->actingAsRole(Role::Owner);
    $employee = Employee::factory()->forOffice($office)->earning(6_000_000, '2025-01-01')->create();

    $this->patch(route('employees.update', $employee), employeePayload($office, ['name' => 'Renamed', 'salary_cents' => 9_900_000, 'salary_from' => '2025-06-01']))
        ->assertSessionHasNoErrors();
    expect(EmployeeSalary::query()->count())->toBe(1);

    $this->post(route('employees.salaries.store', $employee), ['amount_cents' => 7_000_000, 'effective_from' => '2025-07-15'])
        ->assertSessionHasNoErrors();
    // A second row for an existing date is refused: raises are added, never rewritten.
    $this->post(route('employees.salaries.store', $employee), ['amount_cents' => 8_000_000, 'effective_from' => '2025-07-15'])
        ->assertSessionHasErrors('effective_from');

    expect(EmployeeSalary::query()->count())->toBe(2)
        ->and($employee->salaryOn('2025-07-14'))->toBe(6_000_000)
        ->and($employee->salaryOn('2025-07-15'))->toBe(7_000_000);
});

test('the list carries salaries only for people who may see them', function () {
    CarbonImmutable::setTestNow('2026-03-11 10:00:00');
    $office = Office::factory()->create();
    Employee::factory()->forOffice($office)->earning(6_000_000)->create(['name' => 'Karim']);

    $this->actingAsRole(Role::Owner);
    $this->get(route('employees.index'))->assertInertia(fn (Assert $page) => $page
        ->component('employees/Index')
        ->where('canSeeSalaries', true)
        ->where('employees.0.currentSalaryCents', 6_000_000)
        ->where('employees.0.employee.name', 'Karim'));

    $this->actingAsRole(Role::Finance);
    $this->get(route('employees.index'))->assertInertia(fn (Assert $page) => $page
        ->where('canSeeSalaries', false)
        ->where('employees.0.name', 'Karim')
        ->missing('employees.0.currentSalaryCents')
        ->missing('employees.0.employee'));
});

test('the owner can grant finance the salary permission', function () {
    $office = Office::factory()->create();
    $employee = Employee::factory()->forOffice($office)->earning(6_000_000)->create();
    $finance = $this->actingAsRole(Role::Finance);

    $this->get(route('employees.show', $employee))->assertInertia(fn (Assert $page) => $page->where('canSeeSalaries', false));

    config()->set('khazna.finance_can_view_salaries', true);

    $this->get(route('employees.show', $employee))->assertInertia(fn (Assert $page) => $page
        ->where('canSeeSalaries', true)
        ->has('employee.salaryHistory', 1));
    $this->post(route('employees.salaries.store', $employee), ['amount_cents' => 7_000_000, 'effective_from' => '2026-01-01'])
        ->assertSessionHasNoErrors();
});

test('an office-restricted user only manages employees of their own office', function () {
    $mine = Office::factory()->create();
    $theirs = Office::factory()->create();
    $own = Employee::factory()->forOffice($mine)->create(['name' => 'Mine']);
    $other = Employee::factory()->forOffice($theirs)->create(['name' => 'Theirs']);
    $this->actingAs(User::factory()->finance()->forOffice($mine->id)->create());

    $this->get(route('employees.index'))->assertInertia(fn (Assert $page) => $page->has('employees', 1)->where('employees.0.name', 'Mine'));
    $this->get(route('employees.show', $other))->assertForbidden();
    $this->patch(route('employees.update', $other), employeePayload($theirs))->assertForbidden();
    $this->post(route('employees.store'), employeePayload($theirs))->assertForbidden();
    $this->patch(route('employees.update', $own), employeePayload($theirs))->assertForbidden();
});

/**
 * A world with salary figures in every place they could leak from: an employee, a
 * raise, a salary due, a payment and the client and supplier pages beside them.
 */
function salaryWorld(): array
{
    CarbonImmutable::setTestNow('2026-03-11 10:00:00');
    $office = Office::factory()->create();
    $employee = Employee::factory()->forOffice($office)->earning(7_654_321, '2025-01-01')->create(['name' => 'Samir Haddad']);
    $client = Client::factory()->forOffice($office)->create(['name' => 'Clientele']);
    Contract::factory()->create(['client_id' => $client->id, 'office_id' => $office->id]);
    Due::factory()->forClient($client)->create(['due_on' => '2026-03-01', 'period' => '2026-03-01']);
    $salaryDue = Due::factory()->forEmployee($employee, '2026-02-01')->create(['due_on' => '2026-03-05', 'amount_due_cents' => 7_654_321]);
    $account = Account::factory()->forOffice($office)->create();
    Supplier::factory()->create();

    return compact('office', 'employee', 'salaryDue', 'account', 'client');
}

/** @return list<string> */
function leakUrls(array $world): array
{
    return [
        route('home'),
        route('home', ['tab' => 'overdue']),
        route('home', ['tab' => 'today']),
        route('home', ['tab' => 'week']),
        route('home', ['tab' => 'month']),
        route('home', ['tab' => 'attention']),
        route('home', ['kind' => 'salary']),
        route('clients.index'),
        route('clients.show', $world['client']),
        route('contracts.index'),
        route('accounts.index'),
        route('accounts.show', $world['account']),
        route('suppliers.index'),
        route('categories.index'),
        route('employees.index'),
        route('employees.show', $world['employee']),
    ];
}

test('LEAK TEST: finance and account managers never receive a salary, a salary key or a salary due', function (Role $role) {
    $world = salaryWorld();
    $user = $role === Role::AccountManager
        ? User::factory()->accountManager()->forOffice($world['office']->id)->create()
        : User::factory()->finance()->create();
    $this->actingAs($user);

    foreach (leakUrls($world) as $url) {
        $response = $this->get($url);

        // Pages a role may not open at all are refused; anything that opens is searched.
        if (! $response->isSuccessful()) {
            expect($response->getStatusCode())->toBeIn([302, 403]);

            continue;
        }

        $content = $response->getContent();

        expect($content)->not->toContain('7654321', $url)
            ->and($content)->not->toContain('76,543.21', $url)
            ->and($content)->not->toContain('base_salary', $url)
            ->and($content)->not->toContain('currentSalary', $url)
            ->and($content)->not->toContain('salaryHistory', $url)
            ->and($content)->not->toContain('effective_from', $url)
            // No JSON key containing "salary" at all (the `view-salaries` ability has a hyphen, so it does not match).
            ->and(preg_match('/&quot;[A-Za-z_]*[Ss]alar[A-Za-z_]*&quot;:/', $content))->toBe(0, $url);
    }

    // Salary dues are not listed, counted or offered as a filter.
    $tabs = collect($this->get(route('home'))->viewData('page')['props']['tabs'])->keyBy('value');
    expect($tabs->pluck('count')->sum())->toBe($role === Role::AccountManager ? 0 : 1);

    $kinds = collect($this->get(route('home'))->viewData('page')['props']['kinds'])->pluck('value')->all();
    expect($kinds)->not->toContain('salary');
})->with([Role::Finance, Role::AccountManager]);

test('LEAK TEST control: the owner does see the salary everywhere it belongs', function () {
    $world = salaryWorld();
    $this->actingAsRole(Role::Owner);

    expect($this->get(route('employees.index'))->getContent())->toContain('7654321');
    expect($this->get(route('employees.show', $world['employee']))->getContent())->toContain('76,543.21');

    $tabs = collect($this->get(route('home'))->viewData('page')['props']['tabs'])->keyBy('value');
    expect($tabs->pluck('count')->sum())->toBe(2);
});

test('salary changes appear in the audit log for the owner only', function () {
    $employee = Employee::factory()->create();
    $owner = User::factory()->owner()->create();
    $finance = User::factory()->finance()->create();

    $this->actingAs($owner);
    $this->post(route('employees.salaries.store', $employee), ['amount_cents' => 5_500_000, 'effective_from' => '2026-01-01'])->assertSessionHasNoErrors();

    expect(Activity::query()->where('subject_type', EmployeeSalary::class)->where('event', 'created')->count())->toBe(1);
    $this->get(route('audit-log.index'))->assertOk();

    $this->actingAs($finance)->get(route('audit-log.index'))->assertForbidden();
    config()->set('khazna.finance_can_view_salaries', true);
    $this->actingAs($finance)->get(route('audit-log.index'))->assertForbidden();
});
