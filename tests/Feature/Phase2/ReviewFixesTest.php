<?php

use App\Actions\Dues\CancelOpenFutureDues;
use App\Actions\Ledger\RecordManualEntry;
use App\Actions\Ledger\SaveReconciliation;
use App\Actions\Milestones\MarkMilestoneDelivered;
use App\Actions\Milestones\RebalanceMilestones;
use App\Actions\Payments\RecordPayment;
use App\Actions\Payments\ReversePayment;
use App\Actions\Payroll\AddAdjustment;
use App\Actions\Payroll\CloseMonth;
use App\Data\PaymentInput;
use App\Enums\Direction;
use App\Enums\DueStatus;
use App\Enums\PaymentMethod;
use App\Enums\Role;
use App\Models\Account;
use App\Models\AccountEntry;
use App\Models\Category;
use App\Models\Client;
use App\Models\Contract;
use App\Models\Due;
use App\Models\Employee;
use App\Models\Milestone;
use App\Models\Office;
use App\Models\Reconciliation;
use App\Models\User;
use App\Queries\CashReportQuery;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

afterEach(function () {
    CarbonImmutable::setTestNow();
});

function fixNow(): void
{
    CarbonImmutable::setTestNow('2026-04-02 10:00:00');
}

test('the cash report leaves payroll out for anyone without the salary permission', function () {
    fixNow();
    $office = Office::factory()->create();
    $account = Account::factory()->forOffice($office)->create(['opening_balance_cents' => 50_000_000, 'opening_date' => '2026-01-01']);
    $owner = User::factory()->owner()->create();
    $employee = Employee::factory()->forOffice($office)->earning(7_654_321)->create(['name' => 'Samir']);
    app(CloseMonth::class)->handle(CarbonImmutable::parse('2026-03-01'));
    app(RecordPayment::class)->handle($owner, Due::query()->sole(), new PaymentInput(7_654_321, $account->id, PaymentMethod::Ccp, '2026-04-02', null, null, null, false));
    app(RecordManualEntry::class)->handle($owner, $account->id, Direction::Out, 50_000, '2026-04-01', Category::named(Category::BANK_FEES)->id, 'Fee', null);

    $run = fn (User $user): array => (new CashReportQuery)->handle($user, CarbonImmutable::parse('2026-01-01'), CarbonImmutable::parse('2026-04-30'), null, null);

    $ownerView = $run($owner);
    expect($ownerView['includesPayroll'])->toBeTrue()
        ->and($ownerView['totals']['outCents'])->toBe(7_654_321 + 50_000)
        ->and(collect($ownerView['rows'])->pluck('category')->all())->toContain('Salaries');

    $finance = User::factory()->finance()->create();
    $financeView = $run($finance);
    expect($financeView['includesPayroll'])->toBeFalse()
        ->and($financeView['totals']['outCents'])->toBe(50_000)
        ->and(collect($financeView['rows'])->pluck('category')->all())->toBe(['Bank fees'])
        ->and(collect($financeView['offices'])->sole()['outCents'])->toBe(50_000);

    $content = $this->actingAs($finance)->get(route('reports.cash', ['from' => '2026-01-01', 'to' => '2026-04-30']))->getContent();
    expect($content)->not->toContain('7654321')->and($content)->not->toContain('Salaries');
});

test('a salary covered by advances is withdrawn, not left pending at zero', function () {
    fixNow();
    $office = Office::factory()->create();
    $account = Account::factory()->forOffice($office)->create(['opening_balance_cents' => 50_000_000, 'opening_date' => '2026-01-01']);
    $owner = User::factory()->owner()->create();
    $employee = Employee::factory()->forOffice($office)->earning(5_000_000)->create();
    app(CloseMonth::class)->handle(CarbonImmutable::parse('2026-03-01'));
    $due = Due::query()->sole();

    app(AddAdjustment::class)->handle($owner, $employee, [
        'type' => 'advance', 'month' => '2026-03-01', 'amount_cents' => 6_000_000, 'reason' => 'Big advance',
        'paid_on' => '2026-03-20', 'method' => 'cash', 'account_id' => $account->id,
    ]);

    $due->refresh();
    expect($due->status)->toBe(DueStatus::Canceled)
        ->and($due->amount_due_cents)->toBe(0)
        ->and($due->canceled_reason)->toContain('cover the whole salary');

    // It is gone from every open list, and a further adjustment does not churn it.
    expect(Due::query()->visibleTo($owner)->whereIn('status', ['pending', 'partial'])->count())->toBe(0);
    app(AddAdjustment::class)->handle($owner, $employee, ['type' => 'deduction', 'month' => '2026-03-01', 'amount_cents' => 1, 'reason' => 'x']);
    expect($due->refresh()->status)->toBe(DueStatus::Canceled);
});

test('a due reduced to nothing after being paid is settled with a credit, not pending', function () {
    fixNow();
    $office = Office::factory()->create();
    $account = Account::factory()->forOffice($office)->create(['opening_balance_cents' => 50_000_000, 'opening_date' => '2026-01-01']);
    $owner = User::factory()->owner()->create();
    $employee = Employee::factory()->forOffice($office)->earning(5_000_000)->create();
    app(CloseMonth::class)->handle(CarbonImmutable::parse('2026-03-01'));
    $due = Due::query()->sole();
    app(RecordPayment::class)->handle($owner, $due, new PaymentInput(2_000_000, $account->id, PaymentMethod::Cash, '2026-04-02', null, null, null, false));

    app(AddAdjustment::class)->handle($owner, $employee, [
        'type' => 'deduction', 'month' => '2026-03-01', 'amount_cents' => 5_000_000, 'reason' => 'Overpaid earlier', 'correction_reason' => 'Agreed with the employee',
    ]);

    $due->refresh();
    expect($due->amount_due_cents)->toBe(0)->and($due->status)->toBe(DueStatus::Paid)->and($due->creditCents())->toBe(2_000_000);
});

test('canceling a project cancels milestones that are still waiting for delivery', function () {
    fixNow();
    $client = Client::factory()->forOffice(Office::factory()->create())->create();
    $contract = Contract::factory()->create(['client_id' => $client->id, 'office_id' => $client->office_id, 'type' => 'custom_project', 'cycle' => 'once']);
    $dated = Due::factory()->forClient($client)->create(['contract_id' => $contract->id, 'due_on' => '2026-05-01', 'period' => null]);
    $waiting = Due::factory()->forClient($client)->waitingForDelivery()->create(['contract_id' => $contract->id]);

    $count = app(CancelOpenFutureDues::class)->handle($contract, 'Project canceled');

    expect($count)->toBe(2)
        ->and($dated->refresh()->status)->toBe(DueStatus::Canceled)
        ->and($waiting->refresh()->status)->toBe(DueStatus::Canceled)
        ->and($waiting->canceled_by_contract)->toBeTrue();
});

test('the categories the system posts to cannot be renamed or switched off', function () {
    $this->actingAsRole(Role::Owner);

    foreach ([Category::BANK_FEES, Category::RECONCILIATION, Category::SALARIES] as $name) {
        $category = Category::named($name);

        $this->patch(route('categories.update', $category), ['name' => 'Renamed'])->assertSessionHas('error');
        $this->patch(route('categories.update', $category), ['name' => $name, 'active' => false])->assertSessionHas('error');

        expect($category->refresh()->name)->toBe($name)->and($category->active)->toBeTrue();
    }

    // Everything else is free to change.
    $other = Category::named('Hosting');
    $this->patch(route('categories.update', $other), ['name' => 'Cloud hosting'])->assertSessionHasNoErrors();
    expect($other->refresh()->name)->toBe('Cloud hosting');
});

test('the backfill statement marks old payment reversals and nothing else', function () {
    fixNow();
    $office = Office::factory()->create();
    $account = Account::factory()->forOffice($office)->create(['opening_balance_cents' => 0, 'opening_date' => '2026-01-01']);
    $owner = User::factory()->owner()->create();
    $due = Due::factory()->forClient(Client::factory()->forOffice($office)->create())->create(['amount_due_cents' => 1_000_000]);
    $payment = app(RecordPayment::class)->handle($owner, $due, new PaymentInput(1_000_000, $account->id, PaymentMethod::Cash, '2026-04-02', null, null, null, false));
    app(ReversePayment::class)->handle($owner, $payment, 'Wrong');
    AccountEntry::query()->toBase()->update(['is_reversal' => false]);

    // The same statement the migration runs.
    DB::table('account_entries')
        ->where('source_type', 'App\\Models\\Payment')
        ->where('description', 'like', 'Reversal of payment #%')
        ->update(['is_reversal' => true]);

    $entries = AccountEntry::query()->orderBy('id')->get();
    expect($entries->pluck('is_reversal')->all())->toBe([false, true])
        ->and((new CashReportQuery)->handle($owner, CarbonImmutable::parse('2026-01-01'), CarbonImmutable::parse('2026-04-30'), null, null)['totals'])
        ->toMatchArray(['inCents' => 0, 'outCents' => 0]);
});

test('rebalancing refuses a plan that a payment has overtaken', function () {
    fixNow();
    $client = Client::factory()->forOffice(Office::factory()->create())->create();
    $account = Account::factory()->forOffice(Office::query()->findOrFail($client->office_id))->create(['opening_date' => '2026-01-01']);
    $owner = User::factory()->owner()->create();
    $this->actingAs($owner)->post(route('contracts.store'), [
        'type' => 'custom_project', 'client_id' => $client->id, 'office_id' => $client->office_id, 'amount_cents' => 10_000_000, 'starts_on' => '2026-03-01',
        'milestones' => [
            ['label' => 'A', 'mode' => 'percent', 'value' => 50, 'trigger' => 'delivery'],
            ['label' => 'B', 'mode' => 'percent', 'value' => 50, 'trigger' => 'delivery'],
        ],
    ])->assertSessionHasNoErrors();
    $contract = Contract::query()->sole();
    [$a, $b] = $contract->milestones()->get()->all();

    // The user's screen was built when both milestones were unpaid...
    $stale = [
        ['id' => $a->id, 'label' => 'A', 'mode' => 'percent', 'value' => 10],
        ['id' => $b->id, 'label' => 'B', 'mode' => 'percent', 'value' => 90],
    ];

    // ...then someone pays milestone A before they press save.
    app(RecordPayment::class)->handle($owner, Due::query()->where('milestone_id', $a->id)->firstOrFail(), new PaymentInput(5_000_000, $account->id, PaymentMethod::Cash, '2026-04-02', null, null, null, false));

    expect(fn () => app(RebalanceMilestones::class)->handle($contract, $stale, apply: true))->toThrow(ValidationException::class);

    expect($a->refresh()->amount_cents)->toBe(5_000_000)->and(Due::query()->where('milestone_id', $a->id)->value('amount_due_cents'))->toBe(5_000_000)
        ->and((int) $contract->milestones()->sum('amount_cents'))->toBe(10_000_000);
});

test('someone deactivated during a month is still paid for it, and not for the months after', function () {
    fixNow();
    $office = Office::factory()->create();
    $owner = $this->actingAsRole(Role::Owner);
    $employee = Employee::factory()->forOffice($office)->earning(6_000_000, '2025-01-01')->create(['name' => 'Leaver']);
    $stayer = Employee::factory()->forOffice($office)->earning(5_000_000, '2025-01-01')->create();

    // Deactivated on 2 April: the date is kept.
    $this->patch(route('employees.update', $employee), ['name' => 'Leaver', 'office_id' => $office->id, 'status' => 'inactive'])->assertSessionHasNoErrors();
    expect($employee->refresh()->left_on->toDateString())->toBe('2026-04-02');

    $march = collect(app(CloseMonth::class)->preview(CarbonImmutable::parse('2026-03-01')))->pluck('employeeId')->all();
    $april = collect(app(CloseMonth::class)->preview(CarbonImmutable::parse('2026-04-01')))->pluck('employeeId')->all();
    $may = collect(app(CloseMonth::class)->preview(CarbonImmutable::parse('2026-05-01')))->pluck('employeeId')->all();

    expect($march)->toContain($employee->id, $stayer->id)
        ->and($april)->toContain($employee->id)
        ->and($may)->not->toContain($employee->id)->toContain($stayer->id);

    // Reactivating clears the date.
    $this->patch(route('employees.update', $employee), ['name' => 'Leaver', 'office_id' => $office->id, 'status' => 'active']);
    expect($employee->refresh()->left_on)->toBeNull();
});

test('a milestone the client paid in advance can still be marked delivered', function () {
    fixNow();
    $client = Client::factory()->forOffice(Office::factory()->create())->create();
    $contract = Contract::factory()->create(['client_id' => $client->id, 'office_id' => $client->office_id, 'type' => 'custom_project', 'cycle' => 'once']);
    $milestone = Milestone::query()->create(['contract_id' => $contract->id, 'position' => 1, 'label' => 'Prepaid', 'amount_cents' => 1_000_000, 'trigger' => 'delivery']);
    $due = Due::factory()->forClient($client)->waitingForDelivery()->create([
        'contract_id' => $contract->id, 'milestone_id' => $milestone->id, 'status' => DueStatus::Paid, 'amount_due_cents' => 1_000_000, 'amount_paid_cents' => 1_000_000,
    ]);

    $delivered = app(MarkMilestoneDelivered::class)->handle($milestone);

    expect($delivered->delivered_at->toDateString())->toBe('2026-04-02')
        ->and($due->refresh()->due_on)->toBeNull()
        ->and($due->status)->toBe(DueStatus::Paid);

    // A canceled one still cannot be.
    $canceled = Milestone::query()->create(['contract_id' => $contract->id, 'position' => 2, 'label' => 'Dropped', 'amount_cents' => 1, 'trigger' => 'delivery']);
    Due::factory()->forClient($client)->waitingForDelivery()->create(['contract_id' => $contract->id, 'milestone_id' => $canceled->id, 'status' => DueStatus::Canceled]);
    expect(fn () => app(MarkMilestoneDelivered::class)->handle($canceled))->toThrow(ValidationException::class);
});

test('an open reconciliation difference is only recorded if the ledger still disagrees by the same amount', function () {
    fixNow();
    $account = Account::factory()->forOffice(Office::factory()->create())->create(['opening_balance_cents' => 5_000_000, 'opening_date' => '2026-01-01']);
    $owner = User::factory()->owner()->create();
    $open = app(SaveReconciliation::class)->handle($owner, $account->id, '2026-03-01', 4_900_000, null, false, null);
    expect($open->difference_cents)->toBe(-100_000);

    // A fee dated before the count is entered afterwards, so the books now explain part of the gap.
    app(RecordManualEntry::class)->handle($owner, $account->id, Direction::Out, 40_000, '2026-02-20', Category::named(Category::BANK_FEES)->id, 'Late fee', null);

    expect(fn () => app(SaveReconciliation::class)->recordOpenDifference($owner, $open, 'Missing'))
        ->toThrow(ValidationException::class, 'Count the account again');
    expect($open->refresh()->adjustment_entry_id)->toBeNull()
        ->and(Reconciliation::query()->count())->toBe(1);

    // Counting again gives the true difference, which can then be recorded.
    $again = app(SaveReconciliation::class)->handle($owner, $account->id, '2026-03-01', 4_900_000, null, true, 'Still short');
    expect($again->difference_cents)->toBe(-60_000)->and($again->adjustment_entry_id)->not->toBeNull()
        ->and($account->balanceOn('2026-03-01'))->toBe(4_900_000);
});
