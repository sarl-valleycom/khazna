<?php

use App\Actions\Payments\RecordPayment;
use App\Actions\Payroll\PayrollCalculator;
use App\Data\PaymentInput;
use App\Enums\AdjustmentType;
use App\Enums\AttendanceReviewStatus;
use App\Enums\AttendanceStatus;
use App\Enums\PaymentMethod;
use App\Enums\Role;
use App\Models\Account;
use App\Models\AttendanceSession;
use App\Models\Category;
use App\Models\Client;
use App\Models\Due;
use App\Models\Employee;
use App\Models\Office;
use App\Models\SalaryAdjustment;
use App\Models\Supplier;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Smalot\PdfParser\Parser;

uses(RefreshDatabase::class);

afterEach(function () {
    CarbonImmutable::setTestNow();
});

function finText(TestResponse $response): string
{
    $response->assertOk();

    expect($response->headers->get('content-type'))->toBe('application/pdf')
        ->and(substr((string) $response->getContent(), 0, 4))->toBe('%PDF');

    // Fonts join letter pairs (ffi, fi) into one glyph; fold them back so the text reads as typed.
    return Normalizer::normalize((new Parser)->parseContent((string) $response->getContent())->getText(), Normalizer::FORM_KC);
}

function finMarch(): void
{
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-03-20 10:00:00', 'Africa/Algiers'));
}

function finAccount(?Office $office = null): Account
{
    return Account::factory()->forOffice($office ?? Office::factory()->create())->create(['opening_balance_cents' => 99_000_000, 'opening_date' => '2026-01-01']);
}

function finIncome(User $by, Account $account, int $cents, string $on): void
{
    $due = Due::factory()->forClient(Client::factory()->forOffice($account->office)->create())->create(['amount_due_cents' => $cents]);
    app(RecordPayment::class)->handle($by, $due, new PaymentInput($cents, $account->id, PaymentMethod::Cash, $on, null, null, null, false));
}

function finCost(User $by, Account $account, int $cents, string $on, string $category): void
{
    $due = Due::factory()->forSupplier(Supplier::factory()->create(), $account->office)->create(['amount_due_cents' => $cents, 'category_id' => Category::named($category)->id]);
    app(RecordPayment::class)->handle($by, $due, new PaymentInput($cents, $account->id, PaymentMethod::Cash, $on, null, null, null, false));
}

function finEmployee(array $attributes = [], int $salaryCents = 6_000_000): Employee
{
    return Employee::factory()->forOffice(Office::factory()->create())->earning($salaryCents, '2025-01-01')->create($attributes);
}

function finAdjust(Employee $employee, AdjustmentType $type, int $cents, string $reason, string $month = '2026-03-01'): void
{
    SalaryAdjustment::query()->create([
        'employee_id' => $employee->id,
        'month' => $month,
        'type' => $type,
        'amount_cents' => $cents,
        'reason' => $reason,
    ]);
}

// ---- Monthly summary -------------------------------------------------------------------------

test('only owner and finance open the monthly summary', function () {
    $this->get(route('reports.monthly-summary'))->assertRedirect(route('login'));

    $this->actingAsRole(Role::AccountManager);
    $this->get(route('reports.monthly-summary'))->assertForbidden();

    $this->actingAsRole(Role::Finance);
    $this->get(route('reports.monthly-summary'))->assertOk();
});

test('the monthly summary totals money in, money out, outstanding and salaries by hand', function () {
    finMarch();
    $owner = $this->actingAsRole(Role::Owner);
    $account = finAccount();

    finIncome($owner, $account, 2_500_000, '2026-03-05'); // 25 000.00 in
    finIncome($owner, $account, 500_000, '2026-03-06');   //  5 000.00 in
    finCost($owner, $account, 400_000, '2026-03-10', 'Hosting'); // 4 000.00 out
    finIncome($owner, $account, 999_900, '2026-02-10');   // February: not in March

    // A client still owes 15 000.00, already late.
    Due::factory()->forClient(Client::factory()->forOffice($account->office)->create())->create([
        'amount_due_cents' => 1_500_000,
        'due_on' => '2026-03-01',
        'period' => '2026-03-01',
    ]);

    // Salaries: 60 000.00 base + 5 000.00 bonus - 10 000.00 advance = 55 000.00 net.
    $employee = finEmployee(['office_id' => $account->office_id]);
    finAdjust($employee, AdjustmentType::Bonus, 500_000, 'Good month');
    finAdjust($employee, AdjustmentType::Advance, 1_000_000, 'Advance');

    $text = finText($this->get(route('reports.monthly-summary', ['month' => '2026-03'])));

    expect($text)->toContain('Monthly summary')
        ->and($text)->toContain('March 2026')
        ->and($text)->toContain('30,000.00 DA')   // in
        ->and($text)->toContain('4,000.00 DA')    // out
        ->and($text)->toContain('26,000.00 DA')   // result
        ->and($text)->toContain('Hosting')
        ->and($text)->not->toContain('9,999.00')
        ->and($text)->toContain('15,000.00 DA')   // outstanding and late
        ->and($text)->toContain('Net salary total')
        ->and($text)->toContain('60,000.00 DA')
        ->and($text)->toContain('55,000.00 DA');
});

test('without the salary permission the document has no salary block and no payroll', function () {
    finMarch();
    $owner = User::factory()->owner()->create();
    $account = finAccount();
    finIncome($owner, $account, 3_000_000, '2026-03-05');
    $employee = finEmployee(['office_id' => $account->office_id]);
    finAdjust($employee, AdjustmentType::Bonus, 500_000, 'Good month');

    $this->actingAsRole(Role::Finance);
    $text = finText($this->get(route('reports.monthly-summary', ['month' => '2026-03'])));

    expect($text)->toContain('30,000.00 DA')
        ->and($text)->not->toContain('Salaries')
        ->and($text)->not->toContain('Net salary total')
        ->and($text)->not->toContain('60,000.00')
        ->and($text)->toContain('Payroll is not included');
});

test('a bad or future month falls back to the current month', function () {
    finMarch();
    $this->actingAsRole(Role::Owner);

    foreach (['nonsense', '2030-01', '2026-13'] as $month) {
        expect(finText($this->get(route('reports.monthly-summary', ['month' => $month]))))->toContain('March 2026');
    }
});

test('an office can be chosen and appears on the page', function () {
    finMarch();
    $owner = $this->actingAsRole(Role::Owner);
    $algiers = finAccount(Office::factory()->create(['name' => 'Algiers HQ']));
    $oran = finAccount(Office::factory()->create(['name' => 'Oran Office']));
    finIncome($owner, $algiers, 1_000_000, '2026-03-05');
    finIncome($owner, $oran, 2_000_000, '2026-03-06');

    $all = finText($this->get(route('reports.monthly-summary', ['month' => '2026-03'])));
    $one = finText($this->get(route('reports.monthly-summary', ['month' => '2026-03', 'office' => $oran->office_id])));

    expect($all)->toContain('All offices')->and($all)->toContain('30,000.00 DA')
        ->and($one)->toContain('Oran Office')->and($one)->toContain('20,000.00 DA')->and($one)->not->toContain('30,000.00 DA');
});

// ---- Payslip ---------------------------------------------------------------------------------

test('the payslip lists the pay and its adjustments, and the net matches the month close', function () {
    finMarch();
    $this->actingAsRole(Role::Owner);
    $employee = finEmployee(['name' => 'Karim Benali', 'title' => 'Developer', 'expected_minutes_per_day' => 480]);
    finAdjust($employee, AdjustmentType::Bonus, 500_000, 'Delivered early');
    finAdjust($employee, AdjustmentType::Deduction, 272_727, 'Unpaid absence');
    finAdjust($employee, AdjustmentType::Advance, 1_000_000, 'Advance on 5 March');

    $text = finText($this->get(route('employees.payslip', ['employee' => $employee, 'month' => '2026-03'])));

    // 6 000 000 + 500 000 - 272 727 - 1 000 000 = 5 227 273 centimes.
    expect($text)->toContain('Payslip')
        ->and($text)->toContain('Karim Benali')
        ->and($text)->toContain('Developer')
        ->and($text)->toContain('March 2026')
        ->and($text)->toContain('60,000.00 DA')
        ->and($text)->toContain('5,000.00 DA')
        ->and($text)->toContain('2,727.27 DA')
        ->and($text)->toContain('10,000.00 DA')
        ->and($text)->toContain('52,272.73 DA')
        ->and($text)->toContain('Delivered early')
        ->and($text)->toContain('Unpaid absence');

    $net = app(PayrollCalculator::class)->forEmployee($employee, CarbonImmutable::parse('2026-03-01'))['net'];

    expect($net)->toBe(5_227_273);
});

test('the attendance block is information and never changes the pay', function () {
    finMarch();
    $this->actingAsRole(Role::Owner);
    $employee = finEmployee(['expected_minutes_per_day' => 480]);

    // Worked only the first of March (a Sunday): 8 hours of 20 hours expected so far.
    AttendanceSession::factory()->create([
        'employee_id' => $employee->id,
        'started_at' => CarbonImmutable::parse('2026-03-01 09:00:00', 'Africa/Algiers')->utc(),
        'ended_at' => CarbonImmutable::parse('2026-03-01 17:00:00', 'Africa/Algiers')->utc(),
        'status' => AttendanceStatus::Closed,
        'review_status' => AttendanceReviewStatus::Ok,
    ]);

    $text = finText($this->get(route('employees.payslip', ['employee' => $employee, 'month' => '2026-03'])));

    // Sunday 1 to Thursday 5 and Sunday 8 to Thursday 12 and Sunday 15 to Thursday 19 = 15 days to the 20th.
    expect($text)->toContain('Hours worked')
        ->and($text)->toContain('8h 00m')
        ->and($text)->toContain('Hours expected')
        ->and($text)->toContain('120h 00m')
        ->and($text)->toContain('Hours to make up')
        ->and($text)->toContain('does not depend on them')
        // No deduction was entered, so the net is the plain salary.
        ->and($text)->toContain('60,000.00 DA');
});

test('only someone who may see salaries can print a payslip', function () {
    finMarch();
    $employee = finEmployee();

    $this->get(route('employees.payslip', $employee))->assertRedirect(route('login'));

    foreach ([Role::Finance, Role::AccountManager] as $role) {
        $this->actingAsRole($role);
        $this->get(route('employees.payslip', $employee))->assertForbidden();
    }

    $this->actingAsRole(Role::Owner);
    $this->get(route('employees.payslip', $employee))->assertOk();
});

test('an employee with no salary that month has no payslip', function () {
    finMarch();
    $this->actingAsRole(Role::Owner);
    $employee = Employee::factory()->forOffice(Office::factory()->create())->create();

    $this->get(route('employees.payslip', ['employee' => $employee, 'month' => '2026-03']))->assertNotFound();
});

test('a payslip for a month before the first salary is refused, and a bad month falls back', function () {
    finMarch();
    $this->actingAsRole(Role::Owner);
    $employee = finEmployee(salaryCents: 6_000_000);

    $this->get(route('employees.payslip', ['employee' => $employee, 'month' => '2024-06']))->assertNotFound();

    foreach (['nonsense', '2030-01'] as $month) {
        expect(finText($this->get(route('employees.payslip', ['employee' => $employee, 'month' => $month]))))->toContain('March 2026');
    }
});

test('an Arabic employee name gets a payslip with an ASCII file name', function () {
    finMarch();
    $this->actingAsRole(Role::Owner);
    $employee = finEmployee(['name' => 'محمد بن عبد الله']);

    $response = $this->get(route('employees.payslip', $employee));
    finText($response);

    expect($response->headers->get('content-disposition'))->toMatch('/^inline; filename="khazna-payslip-[\x20-\x7e]+-2026-03\.pdf"$/');
});
