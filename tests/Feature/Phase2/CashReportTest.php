<?php

use App\Actions\Ledger\RecordManualEntry;
use App\Actions\Ledger\RecordTransfer;
use App\Actions\Ledger\ReverseTransfer;
use App\Actions\Payments\RecordPayment;
use App\Actions\Payments\ReversePayment;
use App\Data\PaymentInput;
use App\Enums\CategoryKind;
use App\Enums\Direction;
use App\Enums\PaymentMethod;
use App\Models\Account;
use App\Models\Category;
use App\Models\Client;
use App\Models\Due;
use App\Models\Office;
use App\Models\Payment;
use App\Models\Supplier;
use App\Models\User;
use App\Queries\CashReportQuery;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

afterEach(function () {
    CarbonImmutable::setTestNow();
});

function reportNow(): void
{
    CarbonImmutable::setTestNow('2026-03-20 10:00:00');
}

function reportFor(User $user, string $from = '2026-01-01', string $to = '2026-03-31', ?int $office = null, ?int $account = null): array
{
    return (new CashReportQuery)->handle($user, CarbonImmutable::parse($from), CarbonImmutable::parse($to), $office, $account);
}

function clientPayment(User $by, Account $account, int $cents, string $on): Payment
{
    $due = Due::factory()->forClient(Client::factory()->forOffice($account->office)->create())->create(['amount_due_cents' => $cents]);

    return app(RecordPayment::class)->handle($by, $due, new PaymentInput($cents, $account->id, PaymentMethod::Cash, $on, null, null, null, false));
}

function costPayment(User $by, Account $account, int $cents, string $on, string $category): void
{
    $due = Due::factory()->forSupplier(Supplier::factory()->create(), $account->office)->create(['amount_due_cents' => $cents, 'category_id' => Category::named($category)->id]);
    app(RecordPayment::class)->handle($by, $due, new PaymentInput($cents, $account->id, PaymentMethod::Cash, $on, null, null, null, false));
}

test('money in and out are totalled by month and category, with a cash result per month', function () {
    reportNow();
    $account = Account::factory()->forOffice(Office::factory()->create())->create(['opening_balance_cents' => 99_000_000, 'opening_date' => '2026-01-01']);
    $owner = User::factory()->owner()->create();

    clientPayment($owner, $account, 1_000_000, '2026-01-10');        // Jan in
    clientPayment($owner, $account, 2_500_000, '2026-02-03');        // Feb in
    clientPayment($owner, $account, 500_000, '2026-02-20');          // Feb in
    costPayment($owner, $account, 400_000, '2026-01-15', 'Hosting'); // Jan out
    costPayment($owner, $account, 300_000, '2026-02-15', 'Hosting'); // Feb out
    costPayment($owner, $account, 900_000, '2026-02-16', 'Rent');    // Feb out
    app(RecordManualEntry::class)->handle($owner, $account->id, Direction::Out, 50_000, '2026-03-02', Category::named(Category::BANK_FEES)->id, 'Fee', null);
    app(RecordManualEntry::class)->handle($owner, $account->id, Direction::In, 700_000, '2026-03-05', Category::query()->where('kind', CategoryKind::Income)->firstOrFail()->id, 'Injection', null);

    $report = reportFor($owner);
    $rows = collect($report['rows'])->keyBy(fn (array $row): string => $row['direction'].'|'.$row['category']);

    expect($report['months'])->toBe(['2026-01', '2026-02', '2026-03'])
        ->and($rows['in|Client payments']['months'])->toBe(['2026-01' => 1_000_000, '2026-02' => 3_000_000, '2026-03' => 0])
        ->and($rows['in|Other income']['months']['2026-03'])->toBe(700_000)
        ->and($rows['out|Hosting']['months'])->toBe(['2026-01' => 400_000, '2026-02' => 300_000, '2026-03' => 0])
        ->and($rows['out|Rent']['totalCents'])->toBe(900_000)
        ->and($rows['out|Bank fees']['months']['2026-03'])->toBe(50_000)
        ->and($report['totals']['in'])->toBe(['2026-01' => 1_000_000, '2026-02' => 3_000_000, '2026-03' => 700_000])
        ->and($report['totals']['out'])->toBe(['2026-01' => 400_000, '2026-02' => 1_200_000, '2026-03' => 50_000])
        ->and($report['totals']['result'])->toBe(['2026-01' => 600_000, '2026-02' => 1_800_000, '2026-03' => 650_000])
        ->and($report['totals']['inCents'])->toBe(4_700_000)
        ->and($report['totals']['outCents'])->toBe(1_650_000)
        ->and($report['totals']['resultCents'])->toBe(3_050_000);
});

test('a transfer is neither income nor expense, only its fee is', function () {
    reportNow();
    $office = Office::factory()->create();
    $a = Account::factory()->forOffice($office)->create(['opening_balance_cents' => 9_000_000, 'opening_date' => '2026-01-01']);
    $b = Account::factory()->forOffice($office)->create(['opening_balance_cents' => 0, 'opening_date' => '2026-01-01']);
    $owner = User::factory()->owner()->create();
    app(RecordTransfer::class)->handle($owner, $a->id, $b->id, 5_000_000, 20_000, '2026-03-10', null);

    $report = reportFor($owner);

    expect($report['totals']['inCents'])->toBe(0)
        ->and($report['totals']['outCents'])->toBe(20_000)
        ->and(collect($report['rows'])->pluck('category')->all())->toBe(['Bank fees']);
});

test('a reversed payment is netted out instead of counting as money in and money out', function () {
    reportNow();
    $account = Account::factory()->forOffice(Office::factory()->create())->create(['opening_balance_cents' => 0, 'opening_date' => '2026-01-01']);
    $owner = User::factory()->owner()->create();
    clientPayment($owner, $account, 1_000_000, '2026-03-02');
    $reversed = clientPayment($owner, $account, 400_000, '2026-03-03');
    app(ReversePayment::class)->handle($owner, $reversed, 'Bounced');

    $report = reportFor($owner);

    expect($report['totals']['inCents'])->toBe(1_000_000)
        ->and($report['totals']['outCents'])->toBe(0)
        ->and($report['totals']['resultCents'])->toBe(1_000_000)
        ->and(collect($report['rows'])->sole()['totalCents'])->toBe(1_000_000);
});

test('the report narrows to an office or one account and to a date range', function () {
    reportNow();
    $one = Office::factory()->create(['name' => 'One']);
    $two = Office::factory()->create(['name' => 'Two']);
    $a = Account::factory()->forOffice($one)->create(['opening_balance_cents' => 0, 'opening_date' => '2026-01-01']);
    $b = Account::factory()->forOffice($two)->create(['opening_balance_cents' => 0, 'opening_date' => '2025-06-01']);
    $owner = User::factory()->owner()->create();
    clientPayment($owner, $a, 1_000_000, '2026-02-10');
    clientPayment($owner, $b, 3_000_000, '2026-02-11');
    clientPayment($owner, $b, 9_000_000, '2025-12-20');   // before the range

    expect(reportFor($owner, '2026-01-01', '2026-03-31')['totals']['inCents'])->toBe(4_000_000)
        ->and(reportFor($owner, '2026-01-01', '2026-03-31', $one->id)['totals']['inCents'])->toBe(1_000_000)
        ->and(reportFor($owner, '2026-01-01', '2026-03-31', null, $b->id)['totals']['inCents'])->toBe(3_000_000)
        ->and(reportFor($owner, '2025-12-01', '2026-03-31')['totals']['inCents'])->toBe(13_000_000)
        ->and(reportFor($owner, '2025-12-01', '2026-03-31')['months'])->toBe(['2025-12', '2026-01', '2026-02', '2026-03']);
});

test('the office report shows in, out, result, balance and inter-office transfers sent and received', function () {
    reportNow();
    $one = Office::factory()->create(['name' => 'Algiers']);
    $two = Office::factory()->create(['name' => 'Oran']);
    $a = Account::factory()->forOffice($one)->create(['opening_balance_cents' => 10_000_000, 'opening_date' => '2026-01-01']);
    $b = Account::factory()->forOffice($two)->create(['opening_balance_cents' => 2_000_000, 'opening_date' => '2026-01-01']);
    $a2 = Account::factory()->forOffice($one)->create(['opening_balance_cents' => 0, 'opening_date' => '2026-01-01']);
    $owner = User::factory()->owner()->create();
    clientPayment($owner, $a, 1_000_000, '2026-03-02');
    costPayment($owner, $b, 250_000, '2026-03-04', 'Rent');
    app(RecordTransfer::class)->handle($owner, $a->id, $b->id, 4_000_000, 0, '2026-03-06', null);     // inter-office
    app(RecordTransfer::class)->handle($owner, $a->id, $a2->id, 500_000, 0, '2026-03-07', null);      // same office: not inter-office
    $cancelled = app(RecordTransfer::class)->handle($owner, $b->id, $a->id, 700_000, 0, '2026-03-08', null);
    app(ReverseTransfer::class)->handle($owner, $cancelled, 'Mistake');          // reversed: nets to nothing

    $offices = collect(reportFor($owner)['offices'])->keyBy('officeName');

    expect($offices['Algiers'])->toMatchArray([
        'inCents' => 1_000_000, 'outCents' => 0, 'resultCents' => 1_000_000,
        'balanceCents' => 10_000_000 + 1_000_000 - 4_000_000, 'sentCents' => 4_000_000, 'receivedCents' => 0,
    ])->and($offices['Oran'])->toMatchArray([
        'inCents' => 0, 'outCents' => 250_000, 'resultCents' => -250_000,
        'balanceCents' => 2_000_000 - 250_000 + 4_000_000, 'sentCents' => 0, 'receivedCents' => 4_000_000,
    ]);
});

test('the page is for owner and finance, and a restricted user only sees their office', function () {
    reportNow();
    $mine = Office::factory()->create(['name' => 'Mine']);
    $theirs = Office::factory()->create(['name' => 'Theirs']);
    $own = Account::factory()->forOffice($mine)->create(['opening_balance_cents' => 0, 'opening_date' => '2026-01-01']);
    $foreign = Account::factory()->forOffice($theirs)->create(['opening_balance_cents' => 0, 'opening_date' => '2026-01-01']);
    $owner = User::factory()->owner()->create();
    clientPayment($owner, $own, 1_000_000, '2026-03-02');
    clientPayment($owner, $foreign, 7_000_000, '2026-03-02');

    $this->get(route('reports.cash'))->assertRedirect(route('login'));
    $this->actingAs(User::factory()->accountManager()->create())->get(route('reports.cash'))->assertForbidden();

    $this->actingAs($owner)->get(route('reports.cash', ['from' => '2026-01-01', 'to' => '2026-03-31']))->assertInertia(fn (Assert $page) => $page
        ->component('reports/Cash')
        ->where('totals.inCents', 8_000_000)
        ->has('offices', 2)
        ->has('officeOptions', 2));

    $restricted = User::factory()->finance()->forOffice($mine->id)->create();
    $response = $this->actingAs($restricted)->get(route('reports.cash', ['from' => '2026-01-01', 'to' => '2026-03-31', 'office' => $theirs->id, 'account' => $foreign->id]));

    $response->assertInertia(fn (Assert $page) => $page
        ->where('totals.inCents', 0)
        ->where('filters.office', $mine->id)
        ->has('offices', 1)
        ->where('offices.0.officeName', 'Mine')
        ->where('officeOptions', []));
    expect($response->getContent())->not->toContain('Theirs');
});
