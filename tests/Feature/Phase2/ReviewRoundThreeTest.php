<?php

use App\Actions\Ledger\RecordManualEntry;
use App\Actions\Ledger\RecordTransfer;
use App\Actions\Payments\RecordPayment;
use App\Actions\Payments\ReversePayment;
use App\Actions\Payroll\AddAdjustment;
use App\Actions\Payroll\CloseMonth;
use App\Console\Commands\LedgerVerifyCommand;
use App\Data\PaymentInput;
use App\Enums\ContractType;
use App\Enums\Direction;
use App\Enums\PaymentMethod;
use App\Models\Account;
use App\Models\AccountEntry;
use App\Models\Activity;
use App\Models\Category;
use App\Models\Client;
use App\Models\Contract;
use App\Models\Due;
use App\Models\Employee;
use App\Models\Office;
use App\Models\Setting;
use App\Models\Supplier;
use App\Models\User;
use App\Notifications\LedgerMismatchNotification;
use App\Support\Metrics\DashboardMetrics;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

afterEach(function () {
    CarbonImmutable::setTestNow();
});

function thirdNow(): void
{
    CarbonImmutable::setTestNow('2026-04-02 10:00:00');
}

/** @return array{office: Office, account: Account, owner: User} */
function thirdWorld(string $openedOn = '2026-01-01'): array
{
    thirdNow();
    $office = Office::factory()->create();

    return [
        'office' => $office,
        'account' => Account::factory()->forOffice($office)->create(['opening_balance_cents' => 10_000_000, 'opening_date' => $openedOn]),
        'owner' => User::factory()->owner()->create(),
    ];
}

test('money dated exactly on the account opening date is accepted, whatever the server time zone', function () {
    ['office' => $office, 'account' => $account, 'owner' => $owner] = thirdWorld('2026-03-01');
    $other = Account::factory()->forOffice($office)->create(['opening_balance_cents' => 0, 'opening_date' => '2026-03-01']);

    $due = Due::factory()->forClient(Client::factory()->forOffice($office)->create())->create(['amount_due_cents' => 100_000]);
    app(RecordPayment::class)->handle($owner, $due, new PaymentInput(100_000, $account->id, PaymentMethod::Cash, '2026-03-01', null, null, null, false));
    app(RecordTransfer::class)->handle($owner, $account->id, $other->id, 50_000, 0, '2026-03-01', null);
    app(RecordManualEntry::class)->handle($owner, $account->id, Direction::Out, 1_000, '2026-03-01', Category::named(Category::BANK_FEES)->id, 'Fee', null);
    app(AddAdjustment::class)->handle($owner, Employee::factory()->forOffice($office)->earning(5_000_000)->create(), [
        'type' => 'advance', 'month' => '2026-03-01', 'amount_cents' => 20_000, 'reason' => 'Rent', 'paid_on' => '2026-03-01', 'method' => 'cash', 'account_id' => $account->id,
    ]);

    $this->actingAs($owner)->post(route('reconciliations.store'), [
        'account_id' => $other->id, 'as_of' => '2026-03-01', 'counted_balance_cents' => 0, 'note' => 'Opening count',
    ])->assertSessionHasNoErrors();

    expect(AccountEntry::query()->count())->toBe(5);
});

test('reversing a payment after its due was recategorised files the reversal where the money went', function () {
    ['office' => $office, 'account' => $account, 'owner' => $owner] = thirdWorld();
    $hosting = Category::named('Hosting');
    $due = Due::factory()->forSupplier(Supplier::factory()->create(), $office)->create(['amount_due_cents' => 200_000, 'category_id' => $hosting->id]);
    $payment = app(RecordPayment::class)->handle($owner, $due, new PaymentInput(200_000, $account->id, PaymentMethod::Cash, '2026-03-05', null, null, null, false));

    $due->update(['category_id' => Category::named(Category::BANK_FEES)->id]);
    app(ReversePayment::class)->handle($owner, $payment, 'Paid twice');

    $entries = AccountEntry::query()->where('source_type', $payment->getMorphClass())->where('source_id', $payment->id)->orderBy('id')->get();

    expect($entries)->toHaveCount(2)
        ->and($entries->pluck('category_id')->unique()->all())->toBe([$hosting->id]);
});

test('a manual entry under Salaries is payroll: hidden from finance in the list, the choices and the receipt', function () {
    ['account' => $account, 'owner' => $owner] = thirdWorld();
    $salaries = Category::named(Category::SALARIES);

    $entry = app(RecordManualEntry::class)->handle($owner, $account->id, Direction::Out, 400_000, '2026-03-10', $salaries->id, 'Cash bonus run', null);
    $entry->refresh();

    expect(AccountEntry::query()->where('source_id', $entry->id)->where('source_type', $entry->getMorphClass())->sole()->is_payroll)->toBeTrue()
        ->and($entry->isPayroll())->toBeTrue();

    $finance = User::factory()->finance()->create();
    $this->actingAs($finance)->get(route('entries.index'))->assertInertia(fn ($page) => $page
        ->has('entries', 0)
        ->where('categories', fn ($categories) => collect($categories)->doesntContain('name', Category::SALARIES)));
    $this->get(route('entries.receipt', $entry))->assertForbidden();

    $this->actingAs($owner)->get(route('entries.index'))->assertInertia(fn ($page) => $page->has('entries', 1));

    // Reversing it keeps it payroll, so the reversal is hidden as well.
    $this->post(route('entries.reverse', $entry), ['reason' => 'Booked twice'])->assertSessionHasNoErrors();
    expect(AccountEntry::query()->where('source_id', $entry->id)->where('source_type', $entry->getMorphClass())->where('is_payroll', true)->count())->toBe(2);
});

test('a month that has not started cannot be closed', function () {
    ['owner' => $owner] = thirdWorld();

    $this->actingAs($owner)->post(route('payroll.close.store'), ['month' => '2026-05'])->assertSessionHasErrors('month');
    $this->post(route('payroll.close.store'), ['month' => '2026-04'])->assertSessionHasNoErrors();
});

test('closing a month asks the database the same number of questions for three employees as for nine', function () {
    ['office' => $office, 'owner' => $owner] = thirdWorld();
    $count = function () use ($owner): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        app(CloseMonth::class)->preview(CarbonImmutable::parse('2026-03-01'), $owner);
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $queries;
    };

    foreach (range(1, 3) as $i) {
        Employee::factory()->forOffice($office)->earning(5_000_000 + $i, '2025-01-01')->create();
    }
    $few = $count();

    foreach (range(1, 6) as $i) {
        Employee::factory()->forOffice($office)->earning(6_000_000 + $i, '2025-01-01')->create();
    }

    expect($count())->toBe($few);
});

test('a malformed date in the address falls back to the default instead of failing', function () {
    ['owner' => $owner] = thirdWorld();
    $this->actingAs($owner);

    $this->get(route('reports.cash', ['from' => '2026-13-45', 'to' => 'tomorrow']))->assertOk();
    $this->get(route('reports.cash', ['from' => ['x'], 'to' => '2026-02-30']))->assertOk();
    $this->get(route('reconciliations.index', ['as_of' => '2026-99-99']))->assertOk();
    $this->get(route('reconciliations.index', ['as_of' => 'garbage']))->assertOk();
});

test('ledger:verify keeps only the first fifty problems, still counts them all, warns the owner and stays out of the audit log', function () {
    ['account' => $account, 'owner' => $owner] = thirdWorld();
    Notification::fake();

    foreach (range(1, 60) as $i) {
        AccountEntry::query()->create(['account_id' => $account->id, 'direction' => 'in', 'amount_cents' => 100, 'entry_date' => '2026-03-09', 'source_type' => 'App\\Models\\Ghost', 'source_id' => $i]);
    }
    $audited = Activity::query()->count();

    $this->artisan('ledger:verify')->assertFailed();

    $check = json_decode((string) Setting::current(Setting::LEDGER_CHECK), true);

    expect($check['total'])->toBe(60)
        ->and($check['problems'])->toHaveCount(LedgerVerifyCommand::KEEP)
        ->and(Activity::query()->count())->toBe($audited);

    Notification::assertSentTo($owner, LedgerMismatchNotification::class, fn (LedgerMismatchNotification $n): bool => $n->total === 60 && count($n->problems) === LedgerVerifyCommand::KEEP);

    $this->actingAs($owner)->get(route('dashboard'))->assertInertia(fn ($page) => $page
        ->where('ledgerCheck.total', 60)
        ->has('ledgerCheck.problems', LedgerVerifyCommand::KEEP));
});

test('updating a cost contract without sending its type keeps it a cost contract', function () {
    ['office' => $office, 'owner' => $owner] = thirdWorld();
    $supplier = Supplier::factory()->create();
    $category = Category::named('Hosting');
    $payload = [
        'type' => 'recurring_cost', 'supplier_id' => $supplier->id, 'category_id' => $category->id, 'office_id' => $office->id,
        'amount_cents' => 1_500_000, 'cycle' => 'monthly', 'starts_on' => '2026-03-01', 'ends_on' => null, 'auto_renew' => true, 'notes' => null,
    ];

    $this->actingAs($owner)->post(route('contracts.store'), $payload)->assertSessionHasNoErrors();
    $contract = Contract::query()->sole();

    unset($payload['type']);
    $payload['amount_cents'] = 1_800_000;
    $this->patch(route('contracts.update', $contract), $payload)->assertSessionHasNoErrors();

    // A type that is sent has to be the contract's own: a contract never changes kind.
    $this->patch(route('contracts.update', $contract), [...$payload, 'type' => 'saas_renewal', 'amount_cents' => 1_900_000])->assertSessionHasErrors('type');

    $contract->refresh();

    expect($contract->type)->toBe(ContractType::RecurringCost)
        ->and($contract->supplier_id)->toBe($supplier->id)
        ->and($contract->client_id)->toBeNull()
        ->and($contract->amount_cents)->toBe(1_800_000);
});

test('a project milestone falling due is not an upcoming renewal', function () {
    thirdNow();
    $owner = User::factory()->owner()->create();
    $client = Client::factory()->forOffice(Office::factory()->create())->create();

    $this->actingAs($owner)->post(route('contracts.store'), [
        'type' => 'custom_project', 'client_id' => $client->id, 'office_id' => $client->office_id, 'amount_cents' => 10_000_000, 'starts_on' => '2026-03-01',
        'milestones' => [
            ['label' => 'Deposit', 'mode' => 'percent', 'value' => 50, 'trigger' => 'date', 'due_on' => '2026-04-10'],
            ['label' => 'Launch', 'mode' => 'percent', 'value' => 50, 'trigger' => 'delivery'],
        ],
    ])->assertSessionHasNoErrors();

    expect(Due::query()->whereNotNull('milestone_id')->where('due_on', '2026-04-10')->count())->toBe(1)
        ->and((new DashboardMetrics($owner))->upcomingRenewals())->toBe(['count' => 0, 'cents' => 0]);
});
