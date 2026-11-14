<?php

use App\Actions\Ledger\RecordManualEntry;
use App\Actions\Ledger\RecordTransfer;
use App\Enums\CategoryKind;
use App\Enums\Direction;
use App\Enums\Role;
use App\Models\Account;
use App\Models\AccountEntry;
use App\Models\Category;
use App\Models\ManualEntry;
use App\Models\Office;
use App\Models\Transfer;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

afterEach(function () {
    CarbonImmutable::setTestNow();
});

function ledgerNow(): void
{
    CarbonImmutable::setTestNow('2026-03-11 10:00:00');
    Storage::fake('local');
}

function ledgerAccount(Office $office, int $balance, string $name = 'Account'): Account
{
    return Account::factory()->forOffice($office)->create([
        'name' => $name.' '.fake()->unique()->numerify('###'),
        'opening_balance_cents' => $balance,
        'opening_date' => '2026-01-01',
    ]);
}

/**
 * @return array<string, mixed>
 */
function transferPayload(Account $from, Account $to, array $overrides = []): array
{
    return [
        'from_account_id' => $from->id,
        'to_account_id' => $to->id,
        'amount_cents' => 2_000_000,
        'fee_cents' => 20_000,
        'transfer_date' => '2026-03-10',
        'note' => 'Top up the CCP',
        ...$overrides,
    ];
}

test('guests are redirected and account managers are refused', function () {
    ledgerNow();
    $office = Office::factory()->create();
    $account = ledgerAccount($office, 0);
    $other = ledgerAccount($office, 0);

    $this->get(route('transfers.index'))->assertRedirect(route('login'));
    $this->get(route('entries.index'))->assertRedirect(route('login'));

    $this->actingAs(User::factory()->accountManager()->create());
    $this->get(route('transfers.index'))->assertForbidden();
    $this->get(route('entries.index'))->assertForbidden();
    $this->post(route('transfers.store'), transferPayload($account, $other))->assertForbidden();
    $this->post(route('entries.store'), [
        'account_id' => $account->id,
        'direction' => 'out',
        'amount_cents' => 1000,
        'entry_date' => '2026-03-10',
        'category_id' => Category::named(Category::BANK_FEES)->id,
        'description' => 'Fee',
    ])->assertForbidden();
});

test('SRS scenario 19: a 20,000 transfer with a 200 fee leaves 29,800 and 20,000, the fee as an expense', function () {
    ledgerNow();
    $office = Office::factory()->create();
    $cash = ledgerAccount($office, 5_000_000, 'Cash A');
    $ccp = ledgerAccount($office, 0, 'CCP B');
    $this->actingAsRole(Role::Owner);

    $this->post(route('transfers.store'), transferPayload($cash, $ccp))->assertSessionHasNoErrors();

    $transfer = Transfer::query()->sole();
    $entries = AccountEntry::query()->where('source_id', $transfer->id)->orderBy('id')->get();

    expect($cash->balanceCents())->toBe(5_000_000 - 2_000_000 - 20_000)
        ->and($ccp->balanceCents())->toBe(2_000_000)
        ->and($entries)->toHaveCount(3)
        // The two legs are transfers, so they are neither income nor expense...
        ->and($entries->where('is_transfer', true))->toHaveCount(2)
        ->and($entries->where('is_transfer', true)->pluck('category_id')->unique()->all())->toBe([null])
        // ...and the fee is an ordinary expense filed under Bank fees, taken from the source.
        ->and($entries[2]->is_transfer)->toBeFalse()
        ->and($entries[2]->category_id)->toBe(Category::named(Category::BANK_FEES)->id)
        ->and($entries[2]->direction)->toBe(Direction::Out)
        ->and($entries[2]->account_id)->toBe($cash->id)
        ->and($entries[2]->amount_cents)->toBe(20_000);
});

test('the company total only changes by the fee', function () {
    ledgerNow();
    $office = Office::factory()->create();
    $a = ledgerAccount($office, 5_000_000);
    $b = ledgerAccount($office, 1_000_000);
    $before = $a->balanceCents() + $b->balanceCents();
    $this->actingAsRole(Role::Finance);

    $this->post(route('transfers.store'), transferPayload($a, $b, ['fee_cents' => 15_000]))->assertSessionHasNoErrors();

    expect($a->balanceCents() + $b->balanceCents())->toBe($before - 15_000);

    $this->post(route('transfers.store'), transferPayload($b, $a, ['fee_cents' => 0, 'amount_cents' => 500_000]))->assertSessionHasNoErrors();
    expect(AccountEntry::query()->where('source_type', Transfer::class)->count())->toBe(5)
        ->and($a->balanceCents() + $b->balanceCents())->toBe($before - 15_000);
});

test('a transfer cannot use the same account twice, a missing account, or a bad date', function () {
    ledgerNow();
    $office = Office::factory()->create();
    $a = ledgerAccount($office, 5_000_000);
    $b = ledgerAccount($office, 0);
    $inactive = Account::factory()->forOffice($office)->inactive()->create();
    $this->actingAsRole(Role::Owner);

    $this->post(route('transfers.store'), transferPayload($a, $a))->assertSessionHasErrors('to_account_id');
    $this->post(route('transfers.store'), transferPayload($a, $b, ['amount_cents' => 0]))->assertSessionHasErrors('amount_cents');
    $this->post(route('transfers.store'), transferPayload($a, $b, ['fee_cents' => -1]))->assertSessionHasErrors('fee_cents');
    $this->post(route('transfers.store'), transferPayload($a, $b, ['transfer_date' => '2026-03-12']))->assertSessionHasErrors('transfer_date');
    $this->post(route('transfers.store'), transferPayload($a, $b, ['transfer_date' => '2025-12-31']))->assertSessionHasErrors('transfer_date');
    $this->post(route('transfers.store'), transferPayload($a, $inactive))->assertSessionHasErrors('to_account_id');

    expect(Transfer::query()->count())->toBe(0)->and(AccountEntry::query()->count())->toBe(0);
});

test('a reversed transfer restores both balances and keeps the original entries', function () {
    ledgerNow();
    $office = Office::factory()->create();
    $a = ledgerAccount($office, 5_000_000);
    $b = ledgerAccount($office, 1_000_000);
    $this->actingAsRole(Role::Owner);
    $this->post(route('transfers.store'), transferPayload($a, $b));
    $transfer = Transfer::query()->sole();

    $this->post(route('transfers.reverse', $transfer), ['reason' => ''])->assertSessionHasErrors('reason');
    $this->post(route('transfers.reverse', $transfer), ['reason' => 'Wrong account'])->assertSessionHasNoErrors();

    expect($a->balanceCents())->toBe(5_000_000)
        ->and($b->balanceCents())->toBe(1_000_000)
        ->and(AccountEntry::query()->count())->toBe(6)
        ->and($transfer->refresh()->isReversed())->toBeTrue()
        ->and($transfer->reversal_reason)->toBe('Wrong account');

    $this->post(route('transfers.reverse', $transfer), ['reason' => 'Again'])->assertForbidden();
});

test('SRS scenario 20: a transfer between offices is flagged inter-office', function () {
    ledgerNow();
    $one = Office::factory()->create(['name' => 'Office 1']);
    $two = Office::factory()->create(['name' => 'Office 2']);
    $a = ledgerAccount($one, 5_000_000);
    $b = ledgerAccount($two, 0);
    $c = ledgerAccount($one, 0);
    $this->actingAsRole(Role::Owner);

    $this->post(route('transfers.store'), transferPayload($a, $b));
    $this->post(route('transfers.store'), transferPayload($a, $c, ['fee_cents' => 0]));

    $this->get(route('transfers.index'))->assertInertia(fn (Assert $page) => $page
        ->component('accounts/Transfers')
        ->has('transfers', 2)
        ->where('transfers.0.interOffice', false)   // newest first: the same-office one
        ->where('transfers.1.interOffice', true)
        ->where('transfers.1.fromOffice', 'Office 1')
        ->where('transfers.1.toOffice', 'Office 2'));
});

test('an office-restricted user cannot move money into another office', function () {
    ledgerNow();
    $mine = Office::factory()->create();
    $theirs = Office::factory()->create();
    $own = ledgerAccount($mine, 5_000_000);
    $ownTwo = ledgerAccount($mine, 0);
    $foreign = ledgerAccount($theirs, 0);
    $finance = User::factory()->finance()->forOffice($mine->id)->create();
    $this->actingAs($finance);

    $this->post(route('transfers.store'), transferPayload($own, $foreign))->assertSessionHasErrors('to_account_id');
    $this->post(route('transfers.store'), transferPayload($own, $ownTwo))->assertSessionHasNoErrors();

    // An unrestricted owner's inter-office transfer is invisible to them, and cannot be reversed by them.
    $this->actingAs(User::factory()->owner()->create())->post(route('transfers.store'), transferPayload($own, $foreign, ['fee_cents' => 0]));
    $crossOffice = Transfer::query()->where('to_account_id', $foreign->id)->sole();

    $this->actingAs($finance)->get(route('transfers.index'))->assertInertia(fn (Assert $page) => $page->has('transfers', 1));
    $this->actingAs($finance)->post(route('transfers.reverse', $crossOffice), ['reason' => 'No'])->assertForbidden();
});

test('an account can only be deactivated once its balance is zero', function () {
    ledgerNow();
    $office = Office::factory()->create();
    $account = ledgerAccount($office, 100_000);
    $this->actingAsRole(Role::Owner);
    $payload = fn (bool $active) => [
        'name' => $account->name,
        'type' => 'cash',
        'opening_balance_cents' => 100_000,
        'opening_date' => '2026-01-01',
        'active' => $active,
    ];

    $this->patch(route('accounts.update', $account), $payload(false))->assertSessionHasErrors('active');
    expect($account->refresh()->active)->toBeTrue();

    $other = ledgerAccount($office, 0);
    app(RecordTransfer::class)->handle(User::factory()->owner()->create(), $account->id, $other->id, 100_000, 0, '2026-03-10', null);

    $this->patch(route('accounts.update', $account), $payload(false))->assertSessionHasNoErrors();
    expect($account->refresh()->active)->toBeFalse();
});

test('a manual entry needs an account, a matching category and a description, with an optional receipt', function () {
    ledgerNow();
    $office = Office::factory()->create();
    $account = ledgerAccount($office, 1_000_000);
    $owner = $this->actingAsRole(Role::Owner);
    $cost = Category::named(Category::BANK_FEES);
    $income = Category::query()->where('kind', CategoryKind::Income)->firstOrFail();

    $payload = fn (array $o = []) => [
        'account_id' => $account->id,
        'direction' => 'out',
        'amount_cents' => 150_000,
        'entry_date' => '2026-03-09',
        'category_id' => $cost->id,
        'description' => 'Monthly account fee',
        ...$o,
    ];

    $this->post(route('entries.store'), $payload(['description' => '']))->assertSessionHasErrors('description');
    $this->post(route('entries.store'), $payload(['category_id' => $income->id]))->assertSessionHasErrors('category_id');   // money out needs a cost category
    $this->post(route('entries.store'), $payload(['direction' => 'in']))->assertSessionHasErrors('category_id');            // money in needs an income category
    $this->post(route('entries.store'), $payload(['entry_date' => '2026-03-12']))->assertSessionHasErrors('entry_date');
    $this->post(route('entries.store'), $payload(['entry_date' => '2025-12-01']))->assertSessionHasErrors('entry_date');
    $this->post(route('entries.store'), $payload(['receipt' => UploadedFile::fake()->create('x.exe', 10, 'application/x-msdownload')]))->assertSessionHasErrors('receipt');
    expect(ManualEntry::query()->count())->toBe(0);

    $this->post(route('entries.store'), $payload(['receipt' => UploadedFile::fake()->create('fee.pdf', 20, 'application/pdf')]))->assertSessionHasNoErrors();
    $this->post(route('entries.store'), $payload(['direction' => 'in', 'category_id' => $income->id, 'amount_cents' => 400_000, 'description' => 'Owner injection']))->assertSessionHasNoErrors();

    $fee = ManualEntry::query()->orderBy('id')->first();
    $ledger = AccountEntry::query()->where('source_type', ManualEntry::class)->orderBy('id')->get();

    expect($fee->description)->toBe('Monthly account fee')
        ->and($fee->receipt_path)->toStartWith('receipts/')
        ->and($ledger)->toHaveCount(2)
        ->and($ledger[0]->direction)->toBe(Direction::Out)
        ->and($ledger[0]->category_id)->toBe($cost->id)
        ->and($ledger[0]->is_transfer)->toBeFalse()
        ->and($ledger[1]->direction)->toBe(Direction::In)
        ->and($account->balanceCents())->toBe(1_000_000 - 150_000 + 400_000);

    // The receipt is private and follows the entry's visibility.
    $this->get(route('entries.receipt', $fee))->assertOk();
    $this->actingAs(User::factory()->accountManager()->create())->get(route('entries.receipt', $fee))->assertForbidden();
});

test('reversing a manual entry adds an opposite entry with a reason, once', function () {
    ledgerNow();
    $account = ledgerAccount(Office::factory()->create(), 1_000_000);
    $owner = User::factory()->owner()->create();
    $entry = app(RecordManualEntry::class)->handle($owner, $account->id, Direction::Out, 150_000, '2026-03-09', Category::named(Category::BANK_FEES)->id, 'Fee', null);
    $this->actingAs($owner);

    $this->post(route('entries.reverse', $entry), ['reason' => ''])->assertSessionHasErrors('reason');
    $this->post(route('entries.reverse', $entry), ['reason' => 'Booked twice'])->assertSessionHasNoErrors();
    $this->post(route('entries.reverse', $entry), ['reason' => 'Again'])->assertForbidden();

    expect($account->balanceCents())->toBe(1_000_000)
        ->and(AccountEntry::query()->count())->toBe(2)
        ->and($entry->refresh()->reversal_reason)->toBe('Booked twice');
});

test('transfers and manual entries appear in the account statement', function () {
    ledgerNow();
    $office = Office::factory()->create();
    $a = ledgerAccount($office, 5_000_000);
    $b = ledgerAccount($office, 0);
    $owner = User::factory()->owner()->create();
    app(RecordTransfer::class)->handle($owner, $a->id, $b->id, 1_000_000, 10_000, '2026-03-10', null);
    app(RecordManualEntry::class)->handle($owner, $a->id, Direction::Out, 5_000, '2026-03-11', Category::named(Category::BANK_FEES)->id, 'Card fee', null);

    $rows = $this->actingAs($owner)->get(route('accounts.show', $a))->viewData('page')['props']['entries']['data'];

    expect(collect($rows)->pluck('description')->all())->toBe(['Transfer to '.$b->name, 'Transfer fee', 'Card fee'])
        ->and(end($rows)['runningCents'])->toBe($a->balanceCents());
});
