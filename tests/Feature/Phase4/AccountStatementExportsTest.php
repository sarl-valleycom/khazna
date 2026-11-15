<?php

use App\Actions\Ledger\RecordManualEntry;
use App\Actions\Payments\RecordPayment;
use App\Actions\Payroll\CloseMonth;
use App\Data\PaymentInput;
use App\Enums\Direction;
use App\Enums\PaymentMethod;
use App\Enums\Role;
use App\Models\Account;
use App\Models\Category;
use App\Models\Client;
use App\Models\Due;
use App\Models\Employee;
use App\Models\Office;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use Smalot\PdfParser\Parser;

uses(RefreshDatabase::class);

afterEach(function () {
    CarbonImmutable::setTestNow();
});

function stmtWorkbook(TestResponse $response): array
{
    $response->assertOk();
    $book = IOFactory::load($response->baseResponse->getFile()->getPathname());

    return [
        'summary' => $book->getSheetByName('Summary')->toArray(null, false, false, false),
        'entries' => array_values(array_slice($book->getSheetByName('Entries')->toArray(null, false, false, false), 1)),
        'book' => $book,
    ];
}

function stmtPdfText(TestResponse $response): string
{
    $response->assertOk();

    expect($response->headers->get('content-type'))->toBe('application/pdf');

    return Normalizer::normalize((new Parser)->parseContent((string) $response->getContent())->getText(), Normalizer::FORM_KC);
}

/**
 * An account with 10 000.00 DA opening, an income of 2 500.00 on 5 Jan, a cost
 * of 400.00 on 10 Jan and an income of 1 000.00 on 5 Feb.
 *
 * @return array{account: Account, owner: User}
 */
function stmtWorld(): array
{
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-04-20 10:00:00', 'Africa/Algiers'));
    $account = Account::factory()->forOffice(Office::factory()->create(['name' => 'Algiers HQ']))->create([
        'name' => 'CCP Algiers',
        'opening_balance_cents' => 1_000_000,
        'opening_date' => '2025-12-31',
    ]);
    $owner = User::factory()->owner()->create();

    $income = function (int $cents, string $on, string $label) use ($account, $owner): void {
        $due = Due::factory()->forClient(Client::factory()->forOffice($account->office)->create(['name' => $label]))->create(['amount_due_cents' => $cents]);
        app(RecordPayment::class)->handle($owner, $due, new PaymentInput($cents, $account->id, PaymentMethod::Cash, $on, null, null, null, false));
    };

    $income(250_000, '2026-01-05', 'Amal Trading');
    app(RecordManualEntry::class)->handle($owner, $account->id, Direction::Out, 40_000, '2026-01-10', Category::named(Category::BANK_FEES)->id, 'Bank fees', null);
    $income(100_000, '2026-02-05', 'Nour Services');

    return ['account' => $account, 'owner' => $owner];
}

test('guests are sent to the login and an account manager is refused', function () {
    ['account' => $account] = stmtWorld();

    $this->get(route('accounts.statement.export', $account))->assertRedirect(route('login'));
    $this->get(route('accounts.statement.pdf', $account))->assertRedirect(route('login'));

    $this->actingAsRole(Role::AccountManager);
    $this->get(route('accounts.statement.export', $account))->assertForbidden();
    $this->get(route('accounts.statement.pdf', $account))->assertForbidden();
});

test('the Excel statement has a summary and entries with the running balance', function () {
    ['account' => $account] = stmtWorld();
    $this->actingAsRole(Role::Owner);

    $sheets = stmtWorkbook($this->get(route('accounts.statement.export', $account)));
    $summary = collect($sheets['summary'])->pluck(1, 0);

    // 10 000.00 + 2 500.00 - 400.00 + 1 000.00 = 13 100.00
    expect($summary['Account'])->toBe('CCP Algiers')
        ->and($summary['Office'])->toBe('Algiers HQ')
        ->and($summary['Period'])->toBe('All time')
        ->and((float) $summary['Opening balance (DA)'])->toBe(10000.0)
        ->and((float) $summary['Money in (DA)'])->toBe(3500.0)
        ->and((float) $summary['Money out (DA)'])->toBe(400.0)
        ->and((float) $summary['Closing balance (DA)'])->toBe(13100.0);

    $entries = $sheets['entries'];

    expect($entries)->toHaveCount(3)
        ->and(Date::excelToDateTimeObject($entries[0][0])->format('Y-m-d'))->toBe('2026-01-05')
        ->and((float) $entries[0][2])->toBe(2500.0)
        ->and($entries[0][3])->toBeNull()
        ->and((float) $entries[0][4])->toBe(12500.0)
        ->and((float) $entries[1][3])->toBe(400.0)
        ->and((float) $entries[1][4])->toBe(12100.0)
        ->and((float) $entries[2][4])->toBe(13100.0)
        // The last running balance is the account's real balance.
        ->and((int) round(((float) $entries[2][4]) * 100))->toBe($account->balanceCents());
});

test('a period starts from the balance carried over and totals only its own movements', function () {
    ['account' => $account] = stmtWorld();
    $this->actingAsRole(Role::Owner);

    $sheets = stmtWorkbook($this->get(route('accounts.statement.export', ['account' => $account, 'from' => '2026-02-01', 'to' => '2026-02-28'])));
    $summary = collect($sheets['summary'])->pluck(1, 0);

    // Before February: 10 000.00 + 2 500.00 - 400.00 = 12 100.00.
    expect($summary['Period'])->toBe('2026-02-01 to 2026-02-28')
        ->and((float) $summary['Opening balance (DA)'])->toBe(12100.0)
        ->and((float) $summary['Money in (DA)'])->toBe(1000.0)
        ->and((float) $summary['Money out (DA)'])->toBe(0.0)
        ->and((float) $summary['Closing balance (DA)'])->toBe(13100.0)
        ->and($sheets['entries'])->toHaveCount(1)
        ->and((float) $sheets['entries'][0][4])->toBe(13100.0);
});

test('a chunked statement keeps the running balance exact across chunks', function () {
    ['account' => $account, 'owner' => $owner] = stmtWorld();
    $this->actingAsRole(Role::Owner);
    config(['excel.exports.chunk_size' => 2]);

    // Five more entries on the same day, so only the id keeps them in order.
    foreach (range(1, 5) as $n) {
        app(RecordManualEntry::class)->handle($owner, $account->id, Direction::In, 10_000, '2026-03-01', Category::query()->where('kind', 'income')->firstOrFail()->id, 'Tip '.$n, null);
    }

    $entries = stmtWorkbook($this->get(route('accounts.statement.export', $account)))['entries'];
    $balances = collect($entries)->pluck(4)->map(fn ($value) => (int) round($value * 100));

    expect($entries)->toHaveCount(8)
        ->and($balances->last())->toBe($account->balanceCents())
        // Every tip adds exactly 100.00 on top of the 13 100.00 closing balance of the first three.
        ->and($balances->slice(3)->values()->all())->toBe([1_320_000, 1_330_000, 1_340_000, 1_350_000, 1_360_000]);
});

test('finance sees payroll as one anonymous line a day in the Excel and the PDF, the owner sees names', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-04-20 10:00:00', 'Africa/Algiers'));
    $office = Office::factory()->create();
    $account = Account::factory()->forOffice($office)->create(['name' => 'Main', 'opening_balance_cents' => 50_000_000, 'opening_date' => '2026-01-01']);
    $owner = User::factory()->owner()->create();
    Employee::factory()->forOffice($office)->earning(6_000_000, '2025-01-01')->create(['name' => 'Samir Haddad']);
    Employee::factory()->forOffice($office)->earning(4_000_000, '2025-01-01')->create(['name' => 'Nadia Cherif']);
    app(CloseMonth::class)->handle(CarbonImmutable::parse('2026-03-01'));

    foreach (Due::query()->whereNotNull('employee_id')->get() as $due) {
        app(RecordPayment::class)->handle($owner, $due, new PaymentInput($due->amount_due_cents, $account->id, PaymentMethod::Ccp, '2026-04-02', null, null, null, false));
    }

    $this->actingAs(User::factory()->finance()->create());
    $excel = stmtWorkbook($this->get(route('accounts.statement.export', $account)));
    $payroll = collect($excel['entries'])->where(1, 'Payroll')->values();

    expect(json_encode($excel['entries']))->not->toContain('Samir')->and(json_encode($excel['entries']))->not->toContain('Nadia')
        ->and($payroll)->toHaveCount(1)
        ->and((float) $payroll[0][3])->toBe(100000.0)
        ->and((int) round(((float) collect($excel['entries'])->last()[4]) * 100))->toBe($account->balanceCents());

    $pdf = stmtPdfText($this->get(route('accounts.statement.pdf', $account)));

    expect($pdf)->not->toContain('Samir')->and($pdf)->not->toContain('Nadia')->and($pdf)->toContain('Payroll');

    $this->actingAs($owner);
    $ownerExcel = stmtWorkbook($this->get(route('accounts.statement.export', $account)));

    expect(json_encode($ownerExcel['entries']))->toContain('Samir Haddad')->and(json_encode($ownerExcel['entries']))->toContain('Nadia Cherif');
});

test('the PDF statement shows the summary and the movements', function () {
    ['account' => $account] = stmtWorld();
    $this->actingAsRole(Role::Owner);

    $text = stmtPdfText($this->get(route('accounts.statement.pdf', ['account' => $account, 'from' => '2026-01-01', 'to' => '2026-03-31'])));

    expect($text)->toContain('Account statement')
        ->and($text)->toContain('CCP Algiers')
        ->and($text)->toContain('Algiers HQ')
        ->and($text)->toContain('10,000.00 DA')   // opening
        ->and($text)->toContain('2,500.00')
        ->and($text)->toContain('400.00')
        ->and($text)->toContain('13,100.00 DA')   // closing
        ->and($text)->toContain('Bank fees');
});

test('a PDF over the line limit is refused and points to Excel', function () {
    ['account' => $account] = stmtWorld();
    $this->actingAsRole(Role::Owner);

    $rows = [];
    foreach (range(1, 2001) as $n) {
        $rows[] = ['account_id' => $account->id, 'direction' => 'in', 'amount_cents' => 100, 'entry_date' => '2026-03-01', 'source_type' => 'manual_entry', 'source_id' => $n, 'description' => 'Bulk '.$n, 'created_at' => now()];
    }
    foreach (array_chunk($rows, 500) as $chunk) {
        DB::table('account_entries')->insert($chunk);
    }

    $this->get(route('accounts.statement.pdf', $account))->assertStatus(422);
    $this->get(route('accounts.statement.export', $account))->assertOk();
});

test('a reversed period is refused and a finance user stays inside their office', function () {
    ['account' => $account] = stmtWorld();
    $this->actingAsRole(Role::Owner);

    $this->get(route('accounts.statement.export', ['account' => $account, 'from' => '2026-03-01', 'to' => '2026-02-01']))->assertSessionHasErrors('to');

    $otherOffice = Office::factory()->create();
    $this->actingAs(User::factory()->finance()->forOffice($otherOffice->id)->create());

    $this->get(route('accounts.statement.export', $account))->assertForbidden();
    $this->get(route('accounts.statement.pdf', $account))->assertForbidden();
});
