<?php

use App\Actions\Ledger\LedgerWriter;
use App\Actions\Payments\RecordPayment;
use App\Actions\Payments\ReversePayment;
use App\Data\PaymentInput;
use App\Enums\Direction;
use App\Enums\DueStatus;
use App\Enums\PaymentMethod;
use App\Enums\Role;
use App\Models\Account;
use App\Models\AccountEntry;
use App\Models\Activity;
use App\Models\Client;
use App\Models\Due;
use App\Models\Office;
use App\Models\Payment;
use App\Models\User;
use App\Support\AlgerianDate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

/**
 * One office with an account, a client managed by $manager and an open due.
 *
 * @return array{office: Office, account: Account, client: Client, due: Due, manager: User}
 */
function payScenario(int $dueCents = 1_000_000, array $dueOverrides = []): array
{
    $office = Office::factory()->create();
    $manager = User::factory()->accountManager()->forOffice($office->id)->create();
    $account = Account::factory()->forOffice($office)->create(['opening_balance_cents' => 5_000_000]);
    $client = Client::factory()->forManager($manager)->forOffice($office)->create();
    $due = Due::factory()->forClient($client)->create([
        'amount_due_cents' => $dueCents,
        ...$dueOverrides,
    ]);

    return compact('office', 'account', 'client', 'due', 'manager');
}

/**
 * @return array<string, mixed>
 */
function payPayload(Account $account, array $overrides = []): array
{
    return [
        'amount_cents' => 1_000_000,
        'account_id' => $account->id,
        'method' => PaymentMethod::Ccp->value,
        'paid_on' => AlgerianDate::todayString(),
        'reference' => 'CCP-2291',
        'notes' => null,
        ...$overrides,
    ];
}

function payInput(Account $account, int $cents, array $overrides = []): PaymentInput
{
    return new PaymentInput(...[
        'amountCents' => $cents,
        'accountId' => $account->id,
        'method' => PaymentMethod::Cash,
        'paidOn' => AlgerianDate::todayString(),
        'reference' => null,
        'notes' => null,
        'receipt' => null,
        'confirmOverpayment' => false,
        ...$overrides,
    ]);
}

test('guests cannot record, reverse or download payments', function () {
    $s = payScenario();
    $payment = Payment::factory()->create(['due_id' => $s['due']->id, 'account_id' => $s['account']->id]);

    $this->post(route('payments.store', $s['due']), [])->assertRedirect(route('login'));
    $this->post(route('payments.reverse', $payment), [])->assertRedirect(route('login'));
    $this->get(route('payments.receipt', $payment))->assertRedirect(route('login'));
});

test('a full payment settles the due and writes exactly one ledger entry', function () {
    $s = payScenario();
    $this->actingAs($s['manager']);

    $this->post(route('payments.store', $s['due']), payPayload($s['account']))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $payment = Payment::query()->sole();
    $entry = AccountEntry::query()->sole();
    $due = $s['due']->refresh();

    expect($payment->amount_cents)->toBe(1_000_000)
        ->and($payment->recorded_by)->toBe($s['manager']->id)
        ->and($payment->method)->toBe(PaymentMethod::Ccp)
        ->and($due->status)->toBe(DueStatus::Paid)
        ->and($due->amount_paid_cents)->toBe(1_000_000)
        ->and($entry->account_id)->toBe($s['account']->id)
        ->and($entry->direction)->toBe(Direction::In)
        ->and($entry->amount_cents)->toBe(1_000_000)
        ->and($entry->source_type)->toBe(Payment::class)
        ->and($entry->source_id)->toBe($payment->id)
        ->and($entry->created_by)->toBe($s['manager']->id)
        ->and($s['account']->balanceCents())->toBe(6_000_000);
});

test('partial payments leave the due partial and the last one settles it', function () {
    $s = payScenario();
    $this->actingAs($s['manager']);

    $this->post(route('payments.store', $s['due']), payPayload($s['account'], ['amount_cents' => 400_000]))
        ->assertSessionHasNoErrors();

    $due = $s['due']->refresh();
    expect($due->status)->toBe(DueStatus::Partial)
        ->and($due->amount_paid_cents)->toBe(400_000)
        ->and($due->remainingCents())->toBe(600_000);

    $this->post(route('payments.store', $s['due']), payPayload($s['account'], ['amount_cents' => 600_000]))
        ->assertSessionHasNoErrors();

    expect($s['due']->refresh()->status)->toBe(DueStatus::Paid)
        ->and(Payment::query()->count())->toBe(2)
        ->and(AccountEntry::query()->count())->toBe(2);
});

test('an overpayment is refused until it is confirmed, then shown as a credit', function () {
    $s = payScenario();
    $this->actingAs($s['manager']);

    $this->post(route('payments.store', $s['due']), payPayload($s['account'], ['amount_cents' => 1_200_000]))
        ->assertSessionHasErrors('confirm_overpayment');

    expect(Payment::query()->count())->toBe(0)
        ->and(AccountEntry::query()->count())->toBe(0)
        ->and($s['due']->refresh()->status)->toBe(DueStatus::Pending);

    $this->post(route('payments.store', $s['due']), payPayload($s['account'], [
        'amount_cents' => 1_200_000,
        'confirm_overpayment' => true,
    ]))->assertSessionHasNoErrors();

    $due = $s['due']->refresh();
    expect($due->status)->toBe(DueStatus::Paid)
        ->and($due->amount_paid_cents)->toBe(1_200_000)
        ->and($due->creditCents())->toBe(200_000);
});

test('a second payment cannot slip past the remaining amount without confirmation', function () {
    $s = payScenario();
    $record = app(RecordPayment::class);

    $record->handle($s['manager'], $s['due'], payInput($s['account'], 700_000));

    expect(fn () => $record->handle($s['manager'], $s['due'], payInput($s['account'], 700_000)))
        ->toThrow(ValidationException::class);

    expect(Payment::query()->count())->toBe(1)
        ->and($s['due']->refresh()->amount_paid_cents)->toBe(700_000);
});

test('the payment date cannot be in the future or before the account opened', function () {
    $s = payScenario();
    $this->actingAs($s['manager']);
    $tomorrow = AlgerianDate::today()->addDay()->toDateString();
    $beforeOpening = $s['account']->opening_date->subDay()->toDateString();

    $this->post(route('payments.store', $s['due']), payPayload($s['account'], ['paid_on' => $tomorrow]))
        ->assertSessionHasErrors('paid_on');

    $this->post(route('payments.store', $s['due']), payPayload($s['account'], ['paid_on' => $beforeOpening]))
        ->assertSessionHasErrors('paid_on');

    expect(Payment::query()->count())->toBe(0)
        ->and(AccountEntry::query()->count())->toBe(0)
        ->and($s['due']->refresh()->amount_paid_cents)->toBe(0);
});

test('a payment into an account the user cannot use is refused', function () {
    $s = payScenario();
    $otherOffice = Office::factory()->create();
    $foreign = Account::factory()->forOffice($otherOffice)->create();
    $inactive = Account::factory()->forOffice($s['office'])->inactive()->create();
    $this->actingAs($s['manager']);

    $this->post(route('payments.store', $s['due']), payPayload($foreign))->assertSessionHasErrors('account_id');
    $this->post(route('payments.store', $s['due']), payPayload($inactive))->assertSessionHasErrors('account_id');

    expect(Payment::query()->count())->toBe(0);
});

test('settled and canceled dues refuse payments', function () {
    $s = payScenario();
    $this->actingAs($s['manager']);
    $s['due']->update(['status' => DueStatus::Canceled, 'canceled_reason' => 'Client left']);

    $this->post(route('payments.store', $s['due']), payPayload($s['account']))->assertForbidden();

    expect(Payment::query()->count())->toBe(0);
});

test('payments are limited to dues the user can see', function () {
    $s = payScenario();
    $other = payScenario();

    $this->actingAs($s['manager'])
        ->post(route('payments.store', $other['due']), payPayload($other['account']))
        ->assertForbidden();

    $restrictedFinance = User::factory()->finance()->forOffice($s['office']->id)->create();
    $this->actingAs($restrictedFinance)
        ->post(route('payments.store', $other['due']), payPayload($other['account']))
        ->assertForbidden();

    expect(Payment::query()->count())->toBe(0);
});

test('validation covers amount, method and receipt rules', function () {
    Storage::fake('local');
    $s = payScenario();
    $this->actingAs($s['manager']);

    $this->post(route('payments.store', $s['due']), payPayload($s['account'], ['amount_cents' => 0]))
        ->assertSessionHasErrors('amount_cents');
    $this->post(route('payments.store', $s['due']), payPayload($s['account'], ['method' => 'crypto']))
        ->assertSessionHasErrors('method');
    $this->post(route('payments.store', $s['due']), payPayload($s['account'], [
        'receipt' => UploadedFile::fake()->create('script.php', 10, 'text/x-php'),
    ]))->assertSessionHasErrors('receipt');
    $this->post(route('payments.store', $s['due']), payPayload($s['account'], [
        'receipt' => UploadedFile::fake()->create('huge.pdf', 6000, 'application/pdf'),
    ]))->assertSessionHasErrors('receipt');

    expect(Payment::query()->count())->toBe(0)
        ->and(Storage::disk('local')->allFiles('receipts'))->toBe([]);
});

test('a receipt is stored privately under a random name and served with fixed headers', function () {
    Storage::fake('local');
    $s = payScenario();
    $owner = User::factory()->owner()->create();

    $this->actingAs($s['manager'])
        ->post(route('payments.store', $s['due']), payPayload($s['account'], [
            'receipt' => UploadedFile::fake()->image('../../my receipt.png'),
        ]))->assertSessionHasNoErrors();

    $payment = Payment::query()->sole();

    expect($payment->receipt_path)->toStartWith('receipts/')
        ->and(basename($payment->receipt_path))->not->toContain('receipt')
        ->and($payment->receipt_mime)->toBe('image/png');
    Storage::disk('local')->assertExists($payment->receipt_path);

    $response = $this->actingAs($owner)->get(route('payments.receipt', $payment))->assertOk();

    expect($response->headers->get('Content-Type'))->toBe('image/png')
        ->and($response->headers->get('X-Content-Type-Options'))->toBe('nosniff')
        ->and($response->headers->get('Cache-Control'))->toContain('private');
});

test('receipts follow the visibility of the payment', function () {
    Storage::fake('local');
    $s = payScenario();
    $other = payScenario();
    $this->actingAs($s['manager'])
        ->post(route('payments.store', $s['due']), payPayload($s['account'], [
            'receipt' => UploadedFile::fake()->create('r.pdf', 100, 'application/pdf'),
        ]));
    $payment = Payment::query()->sole();
    $noReceipt = Payment::factory()->create(['due_id' => $s['due']->id, 'account_id' => $s['account']->id]);

    $this->actingAs($other['manager'])->get(route('payments.receipt', $payment))->assertForbidden();
    $this->actingAs($s['manager'])->get(route('payments.receipt', $payment))->assertOk();
    $this->actingAs($s['manager'])->get(route('payments.receipt', $noReceipt))->assertNotFound();
});

test('only the owner or finance can reverse, with a reason', function () {
    $s = payScenario();
    app(RecordPayment::class)->handle($s['manager'], $s['due'], payInput($s['account'], 1_000_000));
    $payment = Payment::query()->sole();
    $finance = User::factory()->finance()->create();

    $this->actingAs($s['manager'])
        ->post(route('payments.reverse', $payment), ['reason' => 'Mistake'])
        ->assertForbidden();

    $this->actingAs($finance)
        ->post(route('payments.reverse', $payment), ['reason' => ''])
        ->assertSessionHasErrors('reason');

    expect($payment->refresh()->isReversed())->toBeFalse();

    $this->actingAs($finance)
        ->post(route('payments.reverse', $payment), ['reason' => '  Cheque bounced  '])
        ->assertSessionHasNoErrors();

    $payment->refresh();
    expect($payment->isReversed())->toBeTrue()
        ->and($payment->reversal_reason)->toBe('Cheque bounced')
        ->and($payment->reversed_by)->toBe($finance->id);
});

test('reversing adds an opposite entry, keeps the original and recalculates the due', function () {
    $s = payScenario();
    $owner = User::factory()->owner()->create();
    $record = app(RecordPayment::class);
    $first = $record->handle($s['manager'], $s['due'], payInput($s['account'], 400_000));
    $record->handle($s['manager'], $s['due'], payInput($s['account'], 600_000));

    expect($s['due']->refresh()->status)->toBe(DueStatus::Paid);

    app(ReversePayment::class)->handle($owner, $first, 'Wrong client');

    $due = $s['due']->refresh();
    $entries = AccountEntry::query()->where('source_id', $first->id)->orderBy('id')->get();

    expect($due->status)->toBe(DueStatus::Partial)
        ->and($due->amount_paid_cents)->toBe(600_000)
        ->and($entries)->toHaveCount(2)
        ->and($entries[0]->direction)->toBe(Direction::In)
        ->and($entries[1]->direction)->toBe(Direction::Out)
        ->and($entries[1]->amount_cents)->toBe(400_000)
        ->and($s['account']->balanceCents())->toBe(5_000_000 + 600_000);

    app(ReversePayment::class)->handle($owner, Payment::query()->whereKeyNot($first->id)->sole(), 'Also wrong');

    expect($s['due']->refresh()->status)->toBe(DueStatus::Pending)
        ->and($s['account']->balanceCents())->toBe(5_000_000);
});

test('a payment cannot be reversed twice and the reversal is audited', function () {
    $s = payScenario();
    $owner = User::factory()->owner()->create();
    $payment = app(RecordPayment::class)->handle($s['manager'], $s['due'], payInput($s['account'], 1_000_000));

    app(ReversePayment::class)->handle($owner, $payment, 'First');

    expect(fn () => app(ReversePayment::class)->handle($owner, $payment, 'Second'))
        ->toThrow(ValidationException::class);

    expect(AccountEntry::query()->count())->toBe(2);

    $activity = Activity::query()
        ->where('subject_type', Payment::class)
        ->where('event', 'updated')
        ->latest('id')
        ->firstOrFail();

    expect($activity->getProperty('reason'))->toBe('First');
});

test('finance restricted to another office cannot reverse this payment', function () {
    $s = payScenario();
    $payment = app(RecordPayment::class)->handle($s['manager'], $s['due'], payInput($s['account'], 1_000_000));
    $elsewhere = User::factory()->finance()->forOffice(Office::factory()->create()->id)->create();

    $this->actingAs($elsewhere)
        ->post(route('payments.reverse', $payment), ['reason' => 'No'])
        ->assertForbidden();
});

test('a money-out due writes an out entry', function () {
    $s = payScenario(500_000, ['direction' => Direction::Out]);

    app(RecordPayment::class)->handle($s['manager'], $s['due'], payInput($s['account'], 500_000));

    expect(AccountEntry::query()->sole()->direction)->toBe(Direction::Out)
        ->and($s['account']->balanceCents())->toBe(4_500_000);
});

test('invariant: paid amount, ledger entries and balance stay consistent through payments and reversals', function () {
    $s = payScenario(2_000_000);
    $owner = User::factory()->owner()->create();
    $record = app(RecordPayment::class);
    $reverse = app(ReversePayment::class);

    $a = $record->handle($s['manager'], $s['due'], payInput($s['account'], 300_000));
    $b = $record->handle($s['manager'], $s['due'], payInput($s['account'], 900_000));
    $reverse->handle($owner, $a, 'Typo');
    $c = $record->handle($s['manager'], $s['due'], payInput($s['account'], 1_500_000, ['confirmOverpayment' => true]));
    $reverse->handle($owner, $b, 'Duplicate');

    $due = $s['due']->refresh();
    $live = Payment::query()->whereNull('reversed_at')->sum('amount_cents');
    expect($due->amount_paid_cents)->toBe((int) $live)->toBe(1_500_000);

    foreach (Payment::query()->get() as $payment) {
        $expected = $payment->isReversed() ? 2 : 1;
        expect(AccountEntry::query()->where('source_type', Payment::class)->where('source_id', $payment->id)->count())->toBe($expected);
    }

    $net = AccountEntry::query()->get()->sum(fn (AccountEntry $entry): int => $entry->signedCents());
    expect($s['account']->balanceCents())->toBe(5_000_000 + $net)->toBe(5_000_000 + 1_500_000)
        ->and($c->refresh()->isReversed())->toBeFalse();
});

test('ledger entries cannot be updated or deleted and payments cannot be force deleted', function () {
    $s = payScenario();
    app(RecordPayment::class)->handle($s['manager'], $s['due'], payInput($s['account'], 1_000_000));
    $entry = AccountEntry::query()->sole();

    expect(fn () => $entry->update(['amount_cents' => 1]))->toThrow(LogicException::class)
        ->and(fn () => $entry->delete())->toThrow(LogicException::class)
        ->and(fn () => Payment::query()->sole()->forceDelete())->toThrow(LogicException::class);

    expect($entry->refresh()->amount_cents)->toBe(1_000_000);
});

test('the ledger writer refuses entries before the opening date and non-positive amounts', function () {
    $s = payScenario();
    $payment = Payment::factory()->create(['due_id' => $s['due']->id, 'account_id' => $s['account']->id]);
    $writer = app(LedgerWriter::class);

    expect(fn () => $writer->record(
        $s['account'],
        Direction::In,
        100,
        $s['account']->opening_date->subDay(),
        $payment,
        null,
    ))->toThrow(ValidationException::class)
        ->and(fn () => $writer->record($s['account'], Direction::In, 0, AlgerianDate::today(), $payment, null))
        ->toThrow(InvalidArgumentException::class);

    expect(AccountEntry::query()->count())->toBe(0);
});

test('every role can record a payment on a due it can see', function (Role $role) {
    $s = payScenario();
    $user = match ($role) {
        Role::AccountManager => $s['manager'],
        Role::Finance => User::factory()->finance()->create(),
        Role::Owner => User::factory()->owner()->create(),
    };

    $this->actingAs($user)
        ->post(route('payments.store', $s['due']), payPayload($s['account']))
        ->assertSessionHasNoErrors();

    expect(Payment::query()->count())->toBe(1);
})->with([Role::Owner, Role::Finance, Role::AccountManager]);
