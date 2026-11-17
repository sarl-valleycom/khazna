<?php

use App\Actions\Payments\RecordPayment;
use App\Actions\Payments\ReversePayment;
use App\Actions\Payroll\CloseMonth;
use App\Data\PaymentInput;
use App\Enums\Direction;
use App\Enums\DueKind;
use App\Enums\DueStatus;
use App\Enums\PaymentMethod;
use App\Models\Account;
use App\Models\AccountEntry;
use App\Models\Activity;
use App\Models\Category;
use App\Models\Client;
use App\Models\Due;
use App\Models\Employee;
use App\Models\EmployeeSalary;
use App\Models\Office;
use App\Models\SalaryAdjustment;
use App\Models\Setting;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

afterEach(function () {
    CarbonImmutable::setTestNow();
});

function payrollNow(): void
{
    CarbonImmutable::setTestNow('2026-04-02 10:00:00');
}

/**
 * @return array{office: Office, account: Account, owner: User}
 */
function payrollWorld(): array
{
    payrollNow();
    $office = Office::factory()->create();

    return [
        'office' => $office,
        'account' => Account::factory()->forOffice($office)->create(['opening_balance_cents' => 50_000_000, 'opening_date' => '2026-01-01']),
        'owner' => User::factory()->owner()->create(),
    ];
}

function payrollEmployee(Office $office, int $salaryCents, array $attributes = []): Employee
{
    return Employee::factory()->forOffice($office)->earning($salaryCents, '2025-01-01')->create($attributes);
}

/**
 * @return array<string, mixed>
 */
function adjustmentPayload(Employee $employee, array $overrides = []): array
{
    return [
        'employee_id' => $employee->id,
        'type' => 'bonus',
        'month' => '2026-03',
        'amount_cents' => 500_000,
        'reason' => 'Delivered early',
        ...$overrides,
    ];
}

function advancePayload(Employee $employee, Account $account, array $overrides = []): array
{
    return adjustmentPayload($employee, [
        'type' => 'advance',
        'amount_cents' => 1_000_000,
        'reason' => 'Rent',
        'paid_on' => '2026-03-20',
        'method' => 'cash',
        'account_id' => $account->id,
        ...$overrides,
    ]);
}

test('payroll pages need the salary permission', function () {
    ['owner' => $owner] = payrollWorld();
    $finance = User::factory()->finance()->create();
    $manager = User::factory()->accountManager()->create();
    $urls = [route('payroll.adjustments'), route('payroll.close')];

    foreach ([$finance, $manager] as $user) {
        $this->actingAs($user);
        foreach ($urls as $url) {
            $this->get($url)->assertForbidden();
        }
        $this->post(route('payroll.close.store'), ['month' => '2026-03'])->assertForbidden();
        $this->post(route('payroll.adjustments.store'), [])->assertForbidden();
    }

    $this->actingAs($owner);
    foreach ($urls as $url) {
        $this->get($url)->assertOk();
    }

    // The owner may grant finance the permission.
    config()->set('khazna.finance_can_view_salaries', true);
    $this->actingAs($finance)->get(route('payroll.close'))->assertOk();
});

test('SRS scenario 10: salary 60,000 with a 10,000 advance and a 5,000 bonus closes at 55,000', function () {
    ['office' => $office, 'account' => $account, 'owner' => $owner] = payrollWorld();
    $employee = payrollEmployee($office, 6_000_000);
    $this->actingAs($owner);

    $this->post(route('payroll.adjustments.store'), advancePayload($employee, $account))->assertSessionHasNoErrors();
    $this->post(route('payroll.adjustments.store'), adjustmentPayload($employee, ['amount_cents' => 500_000]))->assertSessionHasNoErrors();

    $preview = $this->get(route('payroll.close', ['month' => '2026-03']))->viewData('page')['props']['lines'][0];

    expect($preview['salaryCents'])->toBe(6_000_000)
        ->and($preview['bonusCents'])->toBe(500_000)
        ->and($preview['advanceCents'])->toBe(1_000_000)
        ->and($preview['netCents'])->toBe(5_500_000)
        ->and($preview['state'])->toBe('ready');

    $this->post(route('payroll.close.store'), ['month' => '2026-03'])->assertSessionHasNoErrors();

    $due = Due::query()->sole();
    expect($due->amount_due_cents)->toBe(5_500_000);
});

test('SRS scenario 9: closing the month twice gives one salary due per employee', function () {
    ['office' => $office, 'owner' => $owner] = payrollWorld();
    Setting::put(Setting::SALARY_DUE_DAY, '5');
    $employees = [payrollEmployee($office, 5_000_000), payrollEmployee($office, 6_000_000), payrollEmployee($office, 7_000_000)];
    $this->actingAs($owner);

    $this->post(route('payroll.close.store'), ['month' => '2026-03'])
        ->assertRedirect(route('payroll.close', ['month' => '2026-03']))
        ->assertSessionHas('close_result', ['created' => 3, 'existing' => 0, 'skipped' => 0]);

    $this->post(route('payroll.close.store'), ['month' => '2026-03'])
        ->assertSessionHas('close_result', ['created' => 0, 'existing' => 3, 'skipped' => 0]);

    $dues = Due::query()->where('kind', DueKind::Salary)->orderBy('employee_id')->get();

    expect($dues)->toHaveCount(3)
        ->and($dues->pluck('employee_id')->all())->toBe(collect($employees)->pluck('id')->all())
        ->and($dues->pluck('amount_due_cents')->all())->toBe([5_000_000, 6_000_000, 7_000_000])
        ->and($dues->pluck('direction')->unique()->all())->toBe([Direction::Out])
        ->and($dues->pluck('period')->map->toDateString()->unique()->all())->toBe(['2026-03-01'])
        ->and($dues->pluck('due_on')->map->toDateString()->unique()->all())->toBe(['2026-04-05'])
        ->and($dues->pluck('category_id')->unique()->all())->toBe([Category::named(Category::SALARIES)->id])
        ->and($dues->pluck('office_id')->unique()->all())->toBe([$office->id]);
});

test('the preview writes nothing', function () {
    ['office' => $office, 'owner' => $owner] = payrollWorld();
    payrollEmployee($office, 5_000_000);

    $this->actingAs($owner)->get(route('payroll.close', ['month' => '2026-03']))->assertOk();

    expect(Due::query()->count())->toBe(0);
});

test('month-close skips inactive people, people hired later, people with no salary and a net of nothing', function () {
    ['office' => $office, 'owner' => $owner] = payrollWorld();
    $active = payrollEmployee($office, 5_000_000);
    payrollEmployee($office, 5_000_000, ['status' => 'inactive']);
    payrollEmployee($office, 5_000_000, ['hire_date' => '2026-04-10']);
    $noSalary = Employee::factory()->forOffice($office)->create(['hire_date' => '2025-01-01']);
    $covered = payrollEmployee($office, 1_000_000, ['name' => 'Advanced']);
    $this->actingAs($owner);

    $account = Account::query()->sole();
    $this->post(route('payroll.adjustments.store'), advancePayload($covered, $account, ['amount_cents' => 1_000_000]))->assertSessionHasNoErrors();

    $this->post(route('payroll.close.store'), ['month' => '2026-03'])
        ->assertSessionHas('close_result', ['created' => 1, 'existing' => 0, 'skipped' => 2]);

    expect(Due::query()->pluck('employee_id')->all())->toBe([$active->id])
        ->and($noSalary->dues()->count())->toBe(0)
        ->and($covered->dues()->count())->toBe(0);
});

test('the salary in force on the last day of the month applies, and a raise never rewrites a closed month', function () {
    ['office' => $office, 'owner' => $owner] = payrollWorld();
    $employee = payrollEmployee($office, 6_000_000);
    $this->actingAs($owner);

    // A raise effective mid-March counts for the whole of March (open question 3).
    EmployeeSalary::query()->create(['employee_id' => $employee->id, 'amount_cents' => 7_000_000, 'effective_from' => '2026-03-20']);
    $this->post(route('payroll.close.store'), ['month' => '2026-03']);
    expect(Due::query()->sole()->amount_due_cents)->toBe(7_000_000);

    // Another raise later changes nothing already closed, and the next month picks it up.
    EmployeeSalary::query()->create(['employee_id' => $employee->id, 'amount_cents' => 8_000_000, 'effective_from' => '2026-04-01']);
    $this->post(route('payroll.close.store'), ['month' => '2026-03']);
    expect(Due::query()->whereDate('period', '2026-03-01')->sole()->amount_due_cents)->toBe(7_000_000);

    CarbonImmutable::setTestNow('2026-05-02 10:00:00');
    $this->post(route('payroll.close.store'), ['month' => '2026-04']);
    expect(Due::query()->whereDate('period', '2026-04-01')->sole()->amount_due_cents)->toBe(8_000_000);
});

test('an adjustment made after the close keeps an unpaid salary due in step', function () {
    ['office' => $office, 'owner' => $owner] = payrollWorld();
    $employee = payrollEmployee($office, 6_000_000);
    $this->actingAs($owner);
    $this->post(route('payroll.close.store'), ['month' => '2026-03']);

    $this->post(route('payroll.adjustments.store'), adjustmentPayload($employee, ['type' => 'bonus', 'amount_cents' => 500_000]))->assertSessionHasNoErrors();
    expect(Due::query()->sole()->amount_due_cents)->toBe(6_500_000);

    $this->post(route('payroll.adjustments.store'), adjustmentPayload($employee, ['type' => 'deduction', 'amount_cents' => 200_000, 'reason' => 'Late']))->assertSessionHasNoErrors();
    expect(Due::query()->sole()->amount_due_cents)->toBe(6_300_000);
});

test('once a salary is paid, changing it takes an explicit correction with a reason', function () {
    ['office' => $office, 'account' => $account, 'owner' => $owner] = payrollWorld();
    $employee = payrollEmployee($office, 6_000_000);
    $this->actingAs($owner);
    $this->post(route('payroll.close.store'), ['month' => '2026-03']);
    $due = Due::query()->sole();
    app(RecordPayment::class)->handle($owner, $due, new PaymentInput(6_000_000, $account->id, PaymentMethod::Cash, '2026-04-02', null, null, null, false));
    expect($due->refresh()->status)->toBe(DueStatus::Paid);

    $this->post(route('payroll.adjustments.store'), adjustmentPayload($employee, ['amount_cents' => 500_000]))
        ->assertSessionHasErrors('correction_reason');
    // The whole request rolled back: no adjustment was kept.
    expect(SalaryAdjustment::query()->count())->toBe(0)->and($due->refresh()->amount_due_cents)->toBe(6_000_000);

    $this->post(route('payroll.adjustments.store'), adjustmentPayload($employee, ['amount_cents' => 500_000, 'correction_reason' => 'Bonus agreed after payday']))
        ->assertSessionHasNoErrors();

    $due->refresh();
    expect($due->amount_due_cents)->toBe(6_500_000)
        ->and($due->status)->toBe(DueStatus::Partial)
        ->and($due->remainingCents())->toBe(500_000);

    $activity = Activity::query()->where('subject_type', Due::class)->where('subject_id', $due->id)->where('event', 'updated')->latest('id')->firstOrFail();
    expect($activity->getProperty('reason'))->toBe('Bonus agreed after payday');
});

test('an advance writes exactly one out entry on the chosen account and reduces that month\'s net', function () {
    ['office' => $office, 'account' => $account, 'owner' => $owner] = payrollWorld();
    $employee = payrollEmployee($office, 6_000_000, ['name' => 'Karim']);
    $this->actingAs($owner);

    $this->post(route('payroll.adjustments.store'), advancePayload($employee, $account))->assertSessionHasNoErrors();

    $adjustment = SalaryAdjustment::query()->sole();
    $entry = AccountEntry::query()->sole();

    expect($adjustment->account_id)->toBe($account->id)
        ->and($adjustment->paid_on->toDateString())->toBe('2026-03-20')
        ->and($entry->direction)->toBe(Direction::Out)
        ->and($entry->amount_cents)->toBe(1_000_000)
        ->and($entry->account_id)->toBe($account->id)
        ->and($entry->entry_date->toDateString())->toBe('2026-03-20')
        ->and($entry->source_type)->toBe(SalaryAdjustment::class)
        ->and($entry->source_id)->toBe($adjustment->id)
        ->and($entry->is_payroll)->toBeTrue()
        ->and($entry->category_id)->toBe(Category::named(Category::SALARIES)->id)
        ->and($entry->description)->toBe('Advance: Karim')
        ->and($account->balanceCents())->toBe(49_000_000);
});

test('an advance needs its account, method and date, and the usual ledger rules apply', function () {
    ['office' => $office, 'account' => $account, 'owner' => $owner] = payrollWorld();
    $employee = payrollEmployee($office, 6_000_000);
    $foreign = Account::factory()->forOffice(Office::factory()->create())->create();
    $inactive = Account::factory()->forOffice($office)->inactive()->create();
    $this->actingAs($owner);

    foreach (['paid_on', 'method', 'account_id'] as $field) {
        $payload = advancePayload($employee, $account);
        unset($payload[$field]);
        $this->post(route('payroll.adjustments.store'), $payload)->assertSessionHasErrors($field);
    }

    $this->post(route('payroll.adjustments.store'), advancePayload($employee, $account, ['paid_on' => '2026-04-03']))->assertSessionHasErrors('paid_on');
    $this->post(route('payroll.adjustments.store'), advancePayload($employee, $account, ['paid_on' => '2025-12-31']))->assertSessionHasErrors('paid_on');
    $this->post(route('payroll.adjustments.store'), advancePayload($employee, $inactive))->assertSessionHasErrors('account_id');
    // Only an advance has payment details.
    $this->post(route('payroll.adjustments.store'), adjustmentPayload($employee, ['account_id' => $account->id]))->assertSessionHasErrors('account_id');

    $restricted = User::factory()->owner()->forOffice($office->id)->create();
    $this->actingAs($restricted)->post(route('payroll.adjustments.store'), advancePayload($employee, $foreign))->assertSessionHasErrors('account_id');

    expect(SalaryAdjustment::query()->count())->toBe(0)->and(AccountEntry::query()->count())->toBe(0);
});

test('a bonus sent with empty payment fields, as the form does, is accepted', function () {
    ['office' => $office, 'owner' => $owner] = payrollWorld();
    $employee = payrollEmployee($office, 6_000_000);
    $this->actingAs($owner);

    $this->post(route('payroll.adjustments.store'), adjustmentPayload($employee, [
        'paid_on' => null,
        'method' => null,
        'account_id' => null,
    ]))->assertSessionHasNoErrors();

    expect(SalaryAdjustment::query()->count())->toBe(1);
});

test('the owner can type a different amount for a salary due when closing', function () {
    ['office' => $office, 'owner' => $owner] = payrollWorld();
    $edited = payrollEmployee($office, 6_000_000);
    $plain = payrollEmployee($office, 4_000_000);
    $this->actingAs($owner);

    $this->post(route('payroll.close.store'), [
        'month' => '2026-03',
        'amounts' => [$edited->id => 5_500_000],
    ])->assertSessionHasNoErrors();

    expect(Due::query()->where('employee_id', $edited->id)->value('amount_due_cents'))->toBe(5_500_000)
        ->and(Due::query()->where('employee_id', $plain->id)->value('amount_due_cents'))->toBe(4_000_000);
});

test('closing can also pay each salary from the chosen account', function () {
    ['office' => $office, 'owner' => $owner, 'account' => $account] = payrollWorld();
    $employee = payrollEmployee($office, 6_000_000);
    $this->actingAs($owner);

    $this->post(route('payroll.close.store'), [
        'month' => '2026-03',
        'pay' => ['accounts' => [$employee->id => $account->id], 'method' => 'cash', 'paid_on' => '2026-04-02'],
    ])->assertSessionHasNoErrors();

    $due = Due::query()->where('employee_id', $employee->id)->firstOrFail();

    expect($due->amount_paid_cents)->toBe(6_000_000)
        ->and($due->status)->toBe(DueStatus::Paid)
        ->and(AccountEntry::query()->where('account_id', $account->id)->where('amount_cents', 6_000_000)->exists())->toBeTrue();
});

test('a refused payment while closing leaves the month open', function () {
    ['office' => $office, 'owner' => $owner, 'account' => $account] = payrollWorld();
    $employee = payrollEmployee($office, 6_000_000);
    $this->actingAs($owner);

    $this->post(route('payroll.close.store'), [
        'month' => '2026-03',
        'pay' => ['accounts' => [$employee->id => $account->id + 999], 'method' => 'cash', 'paid_on' => '2026-04-02'],
    ])->assertSessionHasErrors('account_id');

    // An employee with no account chosen refuses the whole close too.
    $this->post(route('payroll.close.store'), [
        'month' => '2026-03',
        'pay' => ['accounts' => [], 'method' => 'cash', 'paid_on' => '2026-04-02'],
    ])->assertSessionHasErrors();

    expect(Due::query()->count())->toBe(0);
});

test('a typed salary amount must be a positive whole number', function () {
    ['office' => $office, 'owner' => $owner] = payrollWorld();
    $employee = payrollEmployee($office, 6_000_000);
    $this->actingAs($owner);

    $this->post(route('payroll.close.store'), ['month' => '2026-03', 'amounts' => [$employee->id => 0]])
        ->assertSessionHasErrors('amounts.'.$employee->id);
    $this->post(route('payroll.close.store'), ['month' => '2026-03', 'amounts' => [$employee->id => 'abc']])
        ->assertSessionHasErrors('amounts.'.$employee->id);

    expect(Due::query()->count())->toBe(0);
});

test('adjustments need a reason, a positive amount and a real month', function () {
    ['office' => $office, 'owner' => $owner] = payrollWorld();
    $employee = payrollEmployee($office, 6_000_000);
    $this->actingAs($owner);

    $this->post(route('payroll.adjustments.store'), adjustmentPayload($employee, ['reason' => '']))->assertSessionHasErrors('reason');
    $this->post(route('payroll.adjustments.store'), adjustmentPayload($employee, ['amount_cents' => 0]))->assertSessionHasErrors('amount_cents');
    $this->post(route('payroll.adjustments.store'), adjustmentPayload($employee, ['month' => '2026-13']))->assertSessionHasErrors('month');
    $this->post(route('payroll.adjustments.store'), adjustmentPayload($employee, ['type' => 'gift']))->assertSessionHasErrors('type');

    $this->get(route('payroll.adjustments', ['month' => '2026-03']))->assertInertia(fn (Assert $page) => $page
        ->component('payroll/Adjustments')
        ->where('month', '2026-03')
        ->has('adjustments', 0));
});

test('paying a salary due writes a payroll entry named for the owner, and reversing keeps the marker', function () {
    ['office' => $office, 'account' => $account, 'owner' => $owner] = payrollWorld();
    payrollEmployee($office, 6_000_000, ['name' => 'Samir']);
    app(CloseMonth::class)->handle(CarbonImmutable::parse('2026-03-01'));
    $due = Due::query()->sole();

    $payment = app(RecordPayment::class)->handle($owner, $due, new PaymentInput(6_000_000, $account->id, PaymentMethod::Ccp, '2026-04-02', null, null, null, false));
    app(ReversePayment::class)->handle($owner, $payment, 'Wrong account');

    $entries = AccountEntry::query()->orderBy('id')->get();

    expect($entries)->toHaveCount(2)
        ->and($entries->pluck('is_payroll')->unique()->all())->toBe([true])
        ->and($entries->pluck('category_id')->unique()->all())->toBe([Category::named(Category::SALARIES)->id])
        ->and($entries[0]->description)->toBe('Salary: Samir')
        ->and($entries[0]->direction)->toBe(Direction::Out)
        ->and($entries[1]->direction)->toBe(Direction::In);
});

test('SRS scenario 23: finance sees payroll in a statement as one anonymous line a day, the owner sees names', function () {
    ['office' => $office, 'account' => $account, 'owner' => $owner] = payrollWorld();
    $a = payrollEmployee($office, 6_000_000, ['name' => 'Samir Haddad']);
    $b = payrollEmployee($office, 4_000_000, ['name' => 'Nadia Cherif']);
    app(CloseMonth::class)->handle(CarbonImmutable::parse('2026-03-01'));
    foreach (Due::query()->orderBy('employee_id')->get() as $due) {
        app(RecordPayment::class)->handle($owner, $due, new PaymentInput($due->amount_due_cents, $account->id, PaymentMethod::Ccp, '2026-04-02', null, null, null, false));
    }
    $this->actingAs($owner)->post(route('payroll.adjustments.store'), advancePayload($a, $account, ['paid_on' => '2026-04-02', 'amount_cents' => 700_000, 'month' => '2026-04']));

    // An ordinary entry on the same day stays visible and named.
    $client = Client::factory()->forOffice($office)->create();
    $sale = Due::factory()->forClient($client)->create(['amount_due_cents' => 300_000]);
    app(RecordPayment::class)->handle($owner, $sale, new PaymentInput(300_000, $account->id, PaymentMethod::Cash, '2026-04-02', null, null, null, false));

    $finance = User::factory()->finance()->create();
    $content = $this->actingAs($finance)->get(route('accounts.show', $account))->assertOk()->getContent();

    expect($content)->not->toContain('Samir')->and($content)->not->toContain('Nadia');

    $rows = $this->get(route('accounts.show', $account))->viewData('page')['props']['entries']['data'];
    $payroll = collect($rows)->where('description', 'Payroll')->values();

    // Two salaries and an advance, all out, all on 2 April: a single line of 110,000...
    expect($payroll)->toHaveCount(1)
        ->and($payroll[0]['outCents'])->toBe(6_000_000 + 4_000_000 + 700_000)
        ->and(collect($rows)->pluck('description')->all())->toContain('Payment: SaaS due '.$sale->due_on->format('d M Y'));

    // The running balance is exact either way.
    $last = end($rows);
    expect($last['runningCents'])->toBe($account->balanceCents());

    // The owner sees every named line.
    $ownerRows = $this->actingAs($owner)->get(route('accounts.show', $account))->viewData('page')['props']['entries']['data'];
    expect(collect($ownerRows)->pluck('description')->all())->toContain('Salary: Samir Haddad', 'Salary: Nadia Cherif', 'Advance: Samir Haddad');
    expect(end($ownerRows)['runningCents'])->toBe($account->balanceCents());
});

test('FR-TRI-08: a salary due in triage offers an advance, and only to people who may see salaries', function () {
    ['office' => $office, 'owner' => $owner] = payrollWorld();
    $employee = payrollEmployee($office, 6_000_000, ['name' => 'Samir']);
    app(CloseMonth::class)->handle(CarbonImmutable::parse('2026-03-01'));
    Due::query()->update(['due_on' => '2026-04-01']);

    $this->actingAs($owner)->get(route('home'))->assertInertia(fn (Assert $page) => $page
        ->where('dues.data.0.kind', 'salary')
        ->where('dues.data.0.employeeId', $employee->id)
        ->where('dues.data.0.period', '2026-03-01')
        ->where('dues.data.0.abilities.advance', true)
        ->where('dues.data.0.abilities.remind', false)
        ->has('payrollEmployees', 1)
        ->has('payrollTypes', 3));

    $finance = User::factory()->finance()->create();
    $this->actingAs($finance)->get(route('home'))->assertInertia(fn (Assert $page) => $page
        ->has('dues.data', 0)
        ->where('payrollEmployees', [])
        ->where('payrollTypes', []));
});

test('the employee page carries what the advance quick action needs, for salary users only', function () {
    ['office' => $office, 'account' => $account, 'owner' => $owner] = payrollWorld();
    $employee = payrollEmployee($office, 6_000_000);

    $this->actingAs($owner)->get(route('employees.show', $employee))->assertInertia(fn (Assert $page) => $page
        ->has('accounts', 1)
        ->where('accounts.0.id', $account->id)
        ->has('adjustmentTypes', 3));

    $this->actingAs(User::factory()->finance()->create())->get(route('employees.show', $employee))->assertInertia(fn (Assert $page) => $page
        ->where('accounts', [])
        ->where('adjustmentTypes', []));
});
