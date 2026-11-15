<?php

use App\Actions\Ledger\RecordManualEntry;
use App\Actions\Payments\RecordPayment;
use App\Actions\Payroll\CloseMonth;
use App\Data\PaymentInput;
use App\Enums\CategoryKind;
use App\Enums\Direction;
use App\Enums\PaymentMethod;
use App\Enums\Role;
use App\Models\Account;
use App\Models\Category;
use App\Models\Client;
use App\Models\Due;
use App\Models\Employee;
use App\Models\Office;
use App\Models\Supplier;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use PhpOffice\PhpSpreadsheet\IOFactory;

uses(RefreshDatabase::class);

afterEach(function () {
    CarbonImmutable::setTestNow();
});

/**
 * @return array{category: list<list<mixed>>, office: list<list<mixed>>}
 */
function cashSheets(TestResponse $response): array
{
    $response->assertOk();
    $book = IOFactory::load($response->baseResponse->getFile()->getPathname());

    return [
        'category' => $book->getSheetByName('By category')->toArray(null, false, false, false),
        'office' => $book->getSheetByName('By office')->toArray(null, false, false, false),
    ];
}

function cashWorld(): array
{
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-03-20 10:00:00', 'Africa/Algiers'));
    $office = Office::factory()->create(['name' => 'Algiers HQ']);
    $account = Account::factory()->forOffice($office)->create(['opening_balance_cents' => 99_000_000, 'opening_date' => '2026-01-01']);
    $owner = User::factory()->owner()->create();

    $income = function (int $cents, string $on) use ($account, $owner): void {
        $due = Due::factory()->forClient(Client::factory()->forOffice($account->office)->create())->create(['amount_due_cents' => $cents]);
        app(RecordPayment::class)->handle($owner, $due, new PaymentInput($cents, $account->id, PaymentMethod::Cash, $on, null, null, null, false));
    };
    $cost = function (int $cents, string $on, string $category) use ($account, $owner): void {
        $due = Due::factory()->forSupplier(Supplier::factory()->create(), $account->office)->create(['amount_due_cents' => $cents, 'category_id' => Category::named($category)->id]);
        app(RecordPayment::class)->handle($owner, $due, new PaymentInput($cents, $account->id, PaymentMethod::Cash, $on, null, null, null, false));
    };

    $income(1_000_000, '2026-01-10');
    $income(2_500_000, '2026-02-03');
    $income(500_000, '2026-02-20');
    $cost(400_000, '2026-01-15', 'Hosting');
    $cost(300_000, '2026-02-15', 'Hosting');
    $cost(900_000, '2026-02-16', 'Rent');
    app(RecordManualEntry::class)->handle($owner, $account->id, Direction::In, 700_000, '2026-03-05', Category::query()->where('kind', CategoryKind::Income)->firstOrFail()->id, 'Injection', null);

    return ['account' => $account, 'owner' => $owner, 'office' => $office];
}

test('guests are sent to the login and an account manager is refused', function () {
    $this->get(route('reports.cash.export'))->assertRedirect(route('login'));

    $this->actingAsRole(Role::AccountManager);
    $this->get(route('reports.cash.export'))->assertForbidden();
});

test('the workbook holds the report by category and month with hand-computed totals', function () {
    cashWorld();
    $this->actingAsRole(Role::Owner);

    $sheets = cashSheets($this->get(route('reports.cash.export', ['from' => '2026-01-01', 'to' => '2026-03-31'])));
    $rows = collect($sheets['category'])->slice(1)->values();
    $byLabel = $rows->mapWithKeys(fn (array $row): array => [$row[0].'|'.$row[1] => $row]);

    expect($sheets['category'][0])->toBe(['Direction', 'Category', '2026-01', '2026-02', '2026-03', 'Total (DA)'])
        // Client payments: Jan 10 000, Feb 25 000 + 5 000 = 30 000.
        ->and(array_map('floatval', array_slice($byLabel['In|Client payments'], 2)))->toBe([10000.0, 30000.0, 0.0, 40000.0])
        ->and(array_map('floatval', array_slice($byLabel['Out|Hosting'], 2)))->toBe([4000.0, 3000.0, 0.0, 7000.0])
        ->and((float) $byLabel['Out|Rent'][5])->toBe(9000.0)
        ->and(array_map('floatval', array_slice($byLabel['Total|Money in'], 2)))->toBe([10000.0, 30000.0, 7000.0, 47000.0])
        ->and(array_map('floatval', array_slice($byLabel['Total|Money out'], 2)))->toBe([4000.0, 12000.0, 0.0, 16000.0])
        ->and(array_map('floatval', array_slice($byLabel['Total|Cash result'], 2)))->toBe([6000.0, 18000.0, 7000.0, 31000.0]);
});

test('the file and the screen show the same numbers for the same filters', function () {
    cashWorld();
    $this->actingAsRole(Role::Owner);
    $filters = ['from' => '2026-02-01', 'to' => '2026-03-31'];

    $screen = null;
    $this->get(route('reports.cash', $filters))->assertInertia(function (Assert $page) use (&$screen): void {
        $screen = $page->toArray()['props'];
    });

    $sheets = cashSheets($this->get(route('reports.cash.export', $filters)));
    $total = collect($sheets['category'])->first(fn (array $row): bool => $row[1] === 'Money in');
    $result = collect($sheets['category'])->first(fn (array $row): bool => $row[1] === 'Cash result');

    expect((int) round(((float) $total[count($total) - 1]) * 100))->toBe($screen['totals']['inCents'])
        ->and((int) round(((float) $result[count($result) - 1]) * 100))->toBe($screen['totals']['resultCents']);
});

test('the office sheet lists the result and the balance of each office', function () {
    ['office' => $office] = cashWorld();
    $this->actingAsRole(Role::Owner);

    $sheets = cashSheets($this->get(route('reports.cash.export', ['from' => '2026-01-01', 'to' => '2026-03-31'])));

    expect($sheets['office'][0][0])->toBe('Office')
        ->and($sheets['office'][1][0])->toBe('Algiers HQ')
        ->and((float) $sheets['office'][1][1])->toBe(47000.0)
        ->and((float) $sheets['office'][1][2])->toBe(16000.0)
        ->and((float) $sheets['office'][1][3])->toBe(31000.0)
        // 990 000.00 opening + 31 000.00 result.
        ->and((float) $sheets['office'][1][4])->toBe(1021000.0)
        ->and($office->name)->toBe('Algiers HQ');
});

test('finance gets no payroll in the totals, and the owner does', function () {
    ['account' => $account, 'owner' => $owner, 'office' => $office] = cashWorld();
    Employee::factory()->forOffice($office)->earning(6_000_000, '2025-01-01')->create(['name' => 'Samir Haddad']);
    app(CloseMonth::class)->handle(CarbonImmutable::parse('2026-02-01'));
    $due = Due::query()->whereNotNull('employee_id')->sole();
    app(RecordPayment::class)->handle($owner, $due, new PaymentInput($due->amount_due_cents, $account->id, PaymentMethod::Ccp, '2026-03-02', null, null, null, false));

    $filters = ['from' => '2026-01-01', 'to' => '2026-03-31'];

    $this->actingAs(User::factory()->finance()->create());
    $finance = cashSheets($this->get(route('reports.cash.export', $filters)))['category'];
    $financeOut = collect($finance)->first(fn (array $row): bool => $row[1] === 'Money out');

    expect(json_encode($finance))->not->toContain('Samir')->and(json_encode($finance))->not->toContain('Salaries')
        ->and((float) $financeOut[5])->toBe(16000.0);

    $this->actingAs($owner);
    $ownerOut = collect(cashSheets($this->get(route('reports.cash.export', $filters)))['category'])->first(fn (array $row): bool => $row[1] === 'Money out');

    // 16 000.00 of costs + the 60 000.00 salary.
    expect((float) $ownerOut[5])->toBe(76000.0);
});

test('reversed dates are swapped and a restricted user stays in their office', function () {
    $world = cashWorld();
    $otherOffice = Office::factory()->create(['name' => 'Oran Office']);
    $otherAccount = Account::factory()->forOffice($otherOffice)->create(['opening_balance_cents' => 0, 'opening_date' => '2026-01-01']);
    $due = Due::factory()->forClient(Client::factory()->forOffice($otherOffice)->create())->create(['amount_due_cents' => 123_400]);
    app(RecordPayment::class)->handle($world['owner'], $due, new PaymentInput(123_400, $otherAccount->id, PaymentMethod::Cash, '2026-03-03', null, null, null, false));

    $this->actingAs(User::factory()->finance()->forOffice($otherOffice->id)->create());
    $sheets = cashSheets($this->get(route('reports.cash.export', ['from' => '2026-03-31', 'to' => '2026-01-01', 'office' => $world['office']->id])));
    $money = collect($sheets['category'])->first(fn (array $row): bool => $row[1] === 'Money in');

    // The URL asked for the other office, but the user is held to Oran: 1 234.00 only.
    expect((float) $money[count($money) - 1])->toBe(1234.0)
        ->and(collect($sheets['office'])->slice(1)->pluck(0)->all())->toBe(['Oran Office']);
});

test('dates typed into the address bar as arrays fall back instead of failing', function () {
    cashWorld();
    $this->actingAsRole(Role::Owner);

    $this->get('/reports/cash/export?from[]=x&to[a]=y&office[]=1&account[]=2')->assertOk();
    $this->get('/reports/cash?from[]=x&to[a]=y')->assertOk();
});
