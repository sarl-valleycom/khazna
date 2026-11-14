<?php

use App\Actions\Ledger\RecordManualEntry;
use App\Actions\Ledger\RecordTransfer;
use App\Actions\Payments\RecordPayment;
use App\Actions\Payroll\AddAdjustment;
use App\Data\PaymentInput;
use App\Enums\AccountType;
use App\Enums\Direction;
use App\Enums\PaymentMethod;
use App\Enums\Role;
use App\Models\Account;
use App\Models\AccountEntry;
use App\Models\Category;
use App\Models\Client;
use App\Models\Due;
use App\Models\Employee;
use App\Models\Office;
use App\Models\Payment;
use App\Models\Reconciliation;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\LedgerMismatchNotification;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

afterEach(function () {
    CarbonImmutable::setTestNow();
});

function recNow(): void
{
    CarbonImmutable::setTestNow('2026-03-11 10:00:00');
}

function recAccount(int $balance, array $attributes = []): Account
{
    return Account::factory()->forOffice(Office::factory()->create())->create([
        'opening_balance_cents' => $balance,
        'opening_date' => '2026-01-01',
        ...$attributes,
    ]);
}

/**
 * @return array<string, mixed>
 */
function recPayload(Account $account, array $overrides = []): array
{
    return [
        'account_id' => $account->id,
        'as_of' => '2026-03-11',
        'counted_balance_cents' => 4_850_000,
        'note' => 'Counted the box',
        ...$overrides,
    ];
}

test('guests are redirected and account managers are refused', function () {
    recNow();
    $account = recAccount(0);

    $this->get(route('reconciliations.index'))->assertRedirect(route('login'));

    $this->actingAs(User::factory()->accountManager()->create());
    $this->get(route('reconciliations.index'))->assertForbidden();
    $this->post(route('reconciliations.store'), recPayload($account))->assertForbidden();
});

test('SRS scenario 22: a count of 48,500 against 50,000 records an adjustment of minus 1,500 and the balance becomes 48,500', function () {
    recNow();
    $account = recAccount(5_000_000);
    $this->actingAsRole(Role::Owner);

    $this->post(route('reconciliations.store'), recPayload($account, ['record_difference' => true, 'reason' => 'Coins missing from the box']))
        ->assertSessionHasNoErrors();

    $reconciliation = Reconciliation::query()->sole();
    $entry = AccountEntry::query()->sole();

    expect($reconciliation->counted_balance_cents)->toBe(4_850_000)
        ->and($reconciliation->system_balance_cents)->toBe(5_000_000)
        ->and($reconciliation->difference_cents)->toBe(-150_000)
        ->and($reconciliation->adjustment_entry_id)->toBe($entry->id)
        ->and($entry->direction)->toBe(Direction::Out)
        ->and($entry->amount_cents)->toBe(150_000)
        ->and($entry->category_id)->toBe(Category::named(Category::RECONCILIATION)->id)
        ->and($entry->entry_date->toDateString())->toBe('2026-03-11')
        ->and($entry->description)->toBe('Reconciliation adjustment: Coins missing from the box')
        ->and($entry->source_type)->toBe(Reconciliation::class)
        ->and($account->balanceCents())->toBe(4_850_000);
});

test('a surplus is recorded as money in', function () {
    recNow();
    $account = recAccount(5_000_000);
    $this->actingAsRole(Role::Finance);

    $this->post(route('reconciliations.store'), recPayload($account, ['counted_balance_cents' => 5_020_000, 'record_difference' => true, 'reason' => 'Found a receipt']));

    expect(AccountEntry::query()->sole()->direction)->toBe(Direction::In)
        ->and($account->balanceCents())->toBe(5_020_000);
});

test('a difference can be left open and recorded later, and a match writes nothing', function () {
    recNow();
    $account = recAccount(5_000_000);
    $owner = $this->actingAsRole(Role::Owner);

    $this->post(route('reconciliations.store'), recPayload($account));
    $open = Reconciliation::query()->sole();

    expect($open->difference_cents)->toBe(-150_000)
        ->and($open->adjustment_entry_id)->toBeNull()
        ->and(AccountEntry::query()->count())->toBe(0)
        ->and($account->balanceCents())->toBe(5_000_000);

    $this->get(route('reconciliations.index', ['account' => $account->id]))->assertInertia(fn (Assert $page) => $page
        ->where('history.0.open', true)
        ->where('history.0.canAdjust', true));

    $this->post(route('reconciliations.adjust', $open), ['reason' => ''])->assertSessionHasErrors('reason');
    $this->post(route('reconciliations.adjust', $open), ['reason' => 'Confirmed missing'])->assertSessionHasNoErrors();
    $this->post(route('reconciliations.adjust', $open), ['reason' => 'Twice'])->assertSessionHasErrors('reason');

    expect($open->refresh()->adjustment_entry_id)->not->toBeNull()
        ->and($account->balanceCents())->toBe(4_850_000);

    // A count that matches records nothing.
    $this->post(route('reconciliations.store'), recPayload($account, ['counted_balance_cents' => 4_850_000, 'record_difference' => true, 'reason' => 'n/a']));
    expect(Reconciliation::query()->count())->toBe(2)->and(AccountEntry::query()->count())->toBe(1);
});

test('recording a difference needs a reason, and the count needs a valid date and account', function () {
    recNow();
    $account = recAccount(5_000_000);
    $foreign = recAccount(0);
    $restricted = User::factory()->finance()->forOffice($account->office_id)->create();
    $this->actingAs($restricted);

    $this->post(route('reconciliations.store'), recPayload($account, ['record_difference' => true]))->assertSessionHasErrors('reason');
    $this->post(route('reconciliations.store'), recPayload($account, ['as_of' => '2026-03-12']))->assertSessionHasErrors('as_of');
    $this->post(route('reconciliations.store'), recPayload($account, ['as_of' => '2025-12-31']))->assertSessionHasErrors('as_of');
    $this->post(route('reconciliations.store'), recPayload($account, ['counted_balance_cents' => null]))->assertSessionHasErrors('counted_balance_cents');
    $this->post(route('reconciliations.store'), recPayload($foreign))->assertSessionHasErrors('account_id');

    expect(Reconciliation::query()->count())->toBe(0);
});

test('the expected balance is the balance at the end of the chosen day, not today\'s', function () {
    recNow();
    $account = recAccount(1_000_000);
    $owner = User::factory()->owner()->create();
    app(RecordManualEntry::class)->handle($owner, $account->id, Direction::Out, 100_000, '2026-02-10', Category::named(Category::BANK_FEES)->id, 'Fee', null);
    app(RecordManualEntry::class)->handle($owner, $account->id, Direction::Out, 250_000, '2026-03-05', Category::named(Category::BANK_FEES)->id, 'Fee', null);

    expect($account->balanceOn('2026-01-31'))->toBe(1_000_000)
        ->and($account->balanceOn('2026-02-10'))->toBe(900_000)
        ->and($account->balanceOn('2026-03-04'))->toBe(900_000)
        ->and($account->balanceOn('2026-03-11'))->toBe(650_000)
        ->and($account->balanceCents())->toBe(650_000);

    $this->actingAs($owner)->get(route('reconciliations.index', ['account' => $account->id, 'as_of' => '2026-02-20']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('systemBalanceCents', 900_000)
            ->where('asOf', '2026-02-20'));
});

test('accounts due for a count are flagged: cash weekly, everything else every thirty days', function () {
    recNow();
    $office = Office::factory()->create();
    $cash = Account::factory()->forOffice($office)->create(['type' => AccountType::Cash, 'opening_date' => '2026-01-01']);
    $bank = Account::factory()->forOffice($office)->create(['type' => AccountType::Bank, 'opening_date' => '2026-01-01']);
    $never = Account::factory()->forOffice($office)->create(['type' => AccountType::Ccp, 'opening_date' => '2026-01-01']);
    $make = fn (Account $account, string $date) => Reconciliation::query()->create(['account_id' => $account->id, 'as_of' => $date, 'counted_balance_cents' => 0, 'system_balance_cents' => 0, 'difference_cents' => 0]);
    $make($cash, '2026-03-01');    // 10 days ago: too long for cash
    $make($bank, '2026-02-20');    // 19 days ago: fine for a bank

    $accounts = collect($this->actingAs(User::factory()->owner()->create())->get(route('reconciliations.index'))->viewData('page')['props']['accounts'])->keyBy('id');

    expect($accounts[$cash->id]['due'])->toBeTrue()
        ->and($accounts[$cash->id]['everyDays'])->toBe(7)
        ->and($accounts[$bank->id]['due'])->toBeFalse()
        ->and($accounts[$bank->id]['everyDays'])->toBe(30)
        ->and($accounts[$never->id]['due'])->toBeTrue()
        ->and($accounts[$never->id]['lastReconciled'])->toBeNull();
});

/**
 * An office with one of every kind of money movement: a payment, an advance, a transfer
 * (with a fee) and a manual entry.
 */
function ledgerWorld(): array
{
    recNow();
    $office = Office::factory()->create();
    $a = Account::factory()->forOffice($office)->create(['opening_balance_cents' => 50_000_000, 'opening_date' => '2026-01-01']);
    $b = Account::factory()->forOffice($office)->create(['opening_balance_cents' => 0, 'opening_date' => '2026-01-01']);
    $owner = User::factory()->owner()->create();
    $due = Due::factory()->forClient(Client::factory()->forOffice($office)->create())->create(['amount_due_cents' => 1_000_000]);
    $payment = app(RecordPayment::class)->handle($owner, $due, new PaymentInput(1_000_000, $a->id, PaymentMethod::Cash, '2026-03-05', null, null, null, false));
    app(AddAdjustment::class)->handle($owner, Employee::factory()->forOffice($office)->earning(5_000_000)->create(), [
        'type' => 'advance', 'month' => '2026-03-01', 'amount_cents' => 200_000, 'reason' => 'Rent', 'paid_on' => '2026-03-06', 'method' => 'cash', 'account_id' => $a->id,
    ]);
    $transfer = app(RecordTransfer::class)->handle($owner, $a->id, $b->id, 3_000_000, 10_000, '2026-03-07', null);
    $manual = app(RecordManualEntry::class)->handle($owner, $a->id, Direction::Out, 5_000, '2026-03-08', Category::named(Category::BANK_FEES)->id, 'Fee', null);

    return compact('owner', 'a', 'b', 'payment', 'transfer', 'manual');
}

test('ledger:verify passes on a healthy ledger and stores the result for the dashboard', function () {
    ledgerWorld();
    Notification::fake();

    $this->artisan('ledger:verify')->assertSuccessful()->expectsOutput('The ledger is consistent.');

    Notification::assertNothingSent();
    $check = json_decode((string) Setting::current(Setting::LEDGER_CHECK), true);
    expect($check['problems'])->toBe([])->and($check['checked_at'])->not->toBeNull();
});

test('ledger:verify detects a payment whose entry was removed directly in the database', function () {
    ['payment' => $payment, 'owner' => $owner] = ledgerWorld();
    Notification::fake();

    AccountEntry::query()->where('source_type', Payment::class)->where('source_id', $payment->id)->toBase()->delete();

    $this->artisan('ledger:verify')
        ->assertFailed()
        ->expectsOutputToContain("Payment #{$payment->id} should have 1 ledger entries but has 0.");

    Notification::assertSentTo($owner, LedgerMismatchNotification::class, fn (LedgerMismatchNotification $n): bool => count($n->problems) === 1);

    // The owner sees it on the dashboard.
    $this->actingAs($owner)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
        ->has('ledgerCheck.problems', 1));

    // Nobody else does.
    $this->actingAs(User::factory()->finance()->create())->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page->where('ledgerCheck', null));
});

test('ledger:verify detects missing transfer legs, doubled entries and orphans', function () {
    ['transfer' => $transfer, 'manual' => $manual, 'a' => $a] = ledgerWorld();
    Notification::fake();

    // One leg of the transfer vanishes.
    AccountEntry::query()->where('source_type', $transfer->getMorphClass())->where('source_id', $transfer->id)->where('direction', 'in')->toBase()->delete();
    // The manual entry is written twice.
    $copy = AccountEntry::query()->where('source_type', $manual->getMorphClass())->where('source_id', $manual->id)->firstOrFail()->replicate();
    $copy->save();
    // An entry with no source at all.
    AccountEntry::query()->create(['account_id' => $a->id, 'direction' => 'in', 'amount_cents' => 100, 'entry_date' => '2026-03-09', 'source_type' => 'App\\Models\\Ghost', 'source_id' => 99]);
    AccountEntry::query()->create(['account_id' => $a->id, 'direction' => 'in', 'amount_cents' => 100, 'entry_date' => '2026-03-09', 'source_type' => $manual->getMorphClass(), 'source_id' => 12345]);

    $output = collect();
    $this->artisan('ledger:verify')->assertFailed();

    $problems = json_decode((string) Setting::current(Setting::LEDGER_CHECK), true)['problems'];

    expect($problems)->toHaveCount(4)
        ->and(collect($problems)->implode(' | '))->toContain("Transfer #{$transfer->id} should have 3 ledger entries but has 2.")
        ->toContain("Manual entry #{$manual->id} should have 1 ledger entries but has 2.")
        ->toContain('unknown source')
        ->toContain('Manual entry #12345, which does not exist');
    unset($output);
});

test('ledger:verify is scheduled daily', function () {
    $events = collect(app(Schedule::class)->events())
        ->filter(fn ($event): bool => str_contains((string) $event->command, 'ledger:verify'));

    expect($events)->toHaveCount(1)->and($events->first()->expression)->toBe('0 3 * * *');
});
