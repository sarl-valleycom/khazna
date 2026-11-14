<?php

use App\Actions\Ledger\RecordTransfer;
use App\Actions\Ledger\ReverseTransfer;
use App\Actions\Milestones\RebalanceMilestones;
use App\Actions\Payments\RecordPayment;
use App\Actions\Payroll\AddAdjustment;
use App\Actions\Payroll\CloseMonth;
use App\Data\PaymentInput;
use App\Enums\ContractStatus;
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
use App\Models\Office;
use App\Models\Supplier;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

afterEach(function () {
    CarbonImmutable::setTestNow();
});

function roundNow(): void
{
    CarbonImmutable::setTestNow('2026-04-02 10:00:00');
}

/** @return array{contract: Contract, client: Client, owner: User} */
function projectWithMilestones(): array
{
    $client = Client::factory()->forOffice(Office::factory()->create())->create();
    $owner = User::factory()->owner()->create();
    test()->actingAs($owner)->post(route('contracts.store'), [
        'type' => 'custom_project', 'client_id' => $client->id, 'office_id' => $client->office_id, 'amount_cents' => 10_000_000, 'starts_on' => '2026-03-01',
        'milestones' => [
            ['label' => 'Deposit', 'mode' => 'percent', 'value' => 40, 'trigger' => 'date', 'due_on' => '2026-05-01'],
            ['label' => 'Beta', 'mode' => 'percent', 'value' => 30, 'trigger' => 'delivery'],
            ['label' => 'Launch', 'mode' => 'percent', 'value' => 30, 'trigger' => 'delivery'],
        ],
    ])->assertSessionHasNoErrors();

    return ['contract' => Contract::query()->sole(), 'client' => $client, 'owner' => $owner];
}

test('pausing a project and reactivating it brings its milestone dues back, and only those the pause withdrew', function () {
    roundNow();
    ['contract' => $contract, 'owner' => $owner] = projectWithMilestones();
    $handByHand = Due::query()->whereHas('milestone', fn ($q) => $q->where('label', 'Launch'))->firstOrFail();
    $handByHand->update(['status' => DueStatus::Canceled, 'canceled_reason' => 'Client dropped it']);

    $this->actingAs($owner)->patch(route('contracts.status', $contract), ['status' => 'paused', 'reason' => 'Waiting on the client'])->assertSessionHasNoErrors();
    expect(Due::query()->whereNotNull('milestone_id')->where('status', DueStatus::Canceled)->count())->toBe(3);

    $this->patch(route('contracts.status', $contract), ['status' => 'active'])->assertSessionHasNoErrors();

    $dues = Due::query()->whereNotNull('milestone_id')->get()->keyBy(fn (Due $due) => $due->description);
    expect($dues['Deposit']->status)->toBe(DueStatus::Pending)
        ->and($dues['Beta']->status)->toBe(DueStatus::Pending)
        ->and($dues['Beta']->due_on)->toBeNull()
        ->and($dues['Launch']->status)->toBe(DueStatus::Canceled);

    // The revived milestone can be delivered again.
    $beta = $contract->milestones()->where('label', 'Beta')->sole();
    $this->post(route('contracts.milestones.deliver', [$contract, $beta]))->assertSessionHasNoErrors();
});

test('a salary withdrawn because advances covered it comes back when a bonus lifts the net', function () {
    roundNow();
    $office = Office::factory()->create();
    $account = Account::factory()->forOffice($office)->create(['opening_balance_cents' => 50_000_000, 'opening_date' => '2026-01-01']);
    $owner = User::factory()->owner()->create();
    $employee = Employee::factory()->forOffice($office)->earning(5_000_000)->create();
    app(CloseMonth::class)->handle(CarbonImmutable::parse('2026-03-01'));
    $add = fn (array $data) => app(AddAdjustment::class)->handle($owner, $employee, ['month' => '2026-03-01', 'reason' => 'x', ...$data]);

    $add(['type' => 'advance', 'amount_cents' => 5_000_000, 'paid_on' => '2026-03-20', 'method' => 'cash', 'account_id' => $account->id]);
    expect(Due::query()->sole()->status)->toBe(DueStatus::Canceled);

    $add(['type' => 'bonus', 'amount_cents' => 800_000]);

    $due = Due::query()->sole();
    expect($due->status)->toBe(DueStatus::Pending)->and($due->amount_due_cents)->toBe(800_000)->and($due->canceled_reason)->toBeNull();
});

test('a salary canceled by hand is not revived by an adjustment', function () {
    roundNow();
    $office = Office::factory()->create();
    $owner = User::factory()->owner()->create();
    $employee = Employee::factory()->forOffice($office)->earning(5_000_000)->create();
    app(CloseMonth::class)->handle(CarbonImmutable::parse('2026-03-01'));
    Due::query()->sole()->update(['status' => DueStatus::Canceled, 'canceled_reason' => 'Paid in cash outside the system']);

    app(AddAdjustment::class)->handle($owner, $employee, ['type' => 'bonus', 'month' => '2026-03-01', 'amount_cents' => 100_000, 'reason' => 'x']);

    expect(Due::query()->sole()->status)->toBe(DueStatus::Canceled);
});

test('a raise cannot start inside a month that is already closed', function () {
    roundNow();
    $office = Office::factory()->create();
    $owner = User::factory()->owner()->create();
    $employee = Employee::factory()->forOffice($office)->earning(10_000_000, '2025-01-01')->create();
    app(CloseMonth::class)->handle(CarbonImmutable::parse('2026-03-01'));
    $this->actingAs($owner);

    $this->post(route('employees.salaries.store', $employee), ['amount_cents' => 12_000_000, 'effective_from' => '2026-03-10'])->assertSessionHasErrors('effective_from');
    $this->post(route('employees.salaries.store', $employee), ['amount_cents' => 12_000_000, 'effective_from' => '2026-03-31'])->assertSessionHasErrors('effective_from');
    $this->post(route('employees.salaries.store', $employee), ['amount_cents' => 12_000_000, 'effective_from' => '2026-01-01'])->assertSessionHasErrors('effective_from');

    // The month after the last closed one is fine.
    $this->post(route('employees.salaries.store', $employee), ['amount_cents' => 12_000_000, 'effective_from' => '2026-04-01'])->assertSessionHasNoErrors();

    expect($employee->salaryOn('2026-03-31'))->toBe(10_000_000)->and(Due::query()->sole()->amount_due_cents)->toBe(10_000_000);
});

test('month-close only covers the employees the user may see', function () {
    roundNow();
    $mine = Office::factory()->create();
    $theirs = Office::factory()->create();
    Employee::factory()->forOffice($mine)->earning(5_000_000)->create(['name' => 'Mine']);
    Employee::factory()->forOffice($theirs)->earning(9_000_000)->create(['name' => 'Theirs']);
    config()->set('khazna.finance_can_view_salaries', true);
    $finance = User::factory()->finance()->forOffice($mine->id)->create();
    $this->actingAs($finance);

    $page = $this->get(route('payroll.close', ['month' => '2026-03']))->assertOk();
    expect(collect($page->viewData('page')['props']['lines'])->pluck('employeeName')->all())->toBe(['Mine'])
        ->and($page->getContent())->not->toContain('Theirs')->and($page->getContent())->not->toContain('9000000');

    $this->post(route('payroll.close.store'), ['month' => '2026-03'])->assertSessionHasNoErrors();
    expect(Due::query()->pluck('office_id')->unique()->all())->toBe([$mine->id]);

    // An unrestricted owner closes everyone.
    $this->actingAs(User::factory()->owner()->create())->post(route('payroll.close.store'), ['month' => '2026-03']);
    expect(Due::query()->count())->toBe(2);
});

test('reversing a transfer locks its accounts in id order, whichever way the money went', function () {
    roundNow();
    $office = Office::factory()->create();
    $low = Account::factory()->forOffice($office)->create(['opening_balance_cents' => 5_000_000, 'opening_date' => '2026-01-01']);
    $high = Account::factory()->forOffice($office)->create(['opening_balance_cents' => 5_000_000, 'opening_date' => '2026-01-01']);
    $owner = User::factory()->owner()->create();
    $downhill = app(RecordTransfer::class)->handle($owner, $high->id, $low->id, 1_000_000, 10_000, '2026-03-10', null);

    $queries = [];
    DB::listen(function ($query) use (&$queries): void {
        if (str_contains($query->sql, 'for update') && str_contains($query->sql, 'from `accounts`')) {
            $queries[] = $query->sql;
        }
    });

    app(ReverseTransfer::class)->handle($owner, $downhill, 'Mistake');

    // One locking read for both accounts, ordered by id (not one lock per entry in entry order).
    expect($queries)->toHaveCount(1)->and($queries[0])->toContain('order by `id` asc')->toContain('for update');
    // The fee is reversed with it, so both accounts are exactly where they started.
    expect($low->balanceCents())->toBe(5_000_000)->and($high->balanceCents())->toBe(5_000_000);
});

test('recategorizing a cost contract moves its open dues, and not the ones already paid', function () {
    roundNow();
    $office = Office::factory()->create();
    $supplier = Supplier::factory()->create();
    $hosting = Category::named('Hosting');
    $tools = Category::named('Tools');
    $account = Account::factory()->forOffice($office)->create(['opening_balance_cents' => 9_000_000, 'opening_date' => '2026-01-01']);
    $owner = $this->actingAsRole(Role::Owner);
    $contract = Contract::factory()->create([
        'type' => 'recurring_cost', 'direction' => 'out', 'client_id' => null, 'supplier_id' => $supplier->id, 'category_id' => $hosting->id,
        'office_id' => $office->id, 'cycle' => 'monthly', 'starts_on' => '2026-01-10', 'amount_cents' => 500_000,
    ]);
    $paid = Due::factory()->forSupplier($supplier, $office)->create(['contract_id' => $contract->id, 'category_id' => $hosting->id, 'due_on' => '2026-03-10', 'period' => '2026-03-10', 'amount_due_cents' => 500_000]);
    app(RecordPayment::class)->handle($owner, $paid, new PaymentInput(500_000, $account->id, PaymentMethod::Cash, '2026-03-10', null, null, null, false));
    $open = Due::factory()->forSupplier($supplier, $office)->create(['contract_id' => $contract->id, 'category_id' => $hosting->id, 'due_on' => '2026-04-10', 'period' => '2026-04-10', 'amount_due_cents' => 500_000]);

    $this->patch(route('contracts.update', $contract), [
        'type' => 'recurring_cost', 'supplier_id' => $supplier->id, 'category_id' => $tools->id, 'office_id' => $office->id,
        'amount_cents' => 500_000, 'cycle' => 'monthly', 'starts_on' => '2026-01-10', 'auto_renew' => true,
    ])->assertSessionHasNoErrors();

    expect($open->refresh()->category_id)->toBe($tools->id)
        ->and($paid->refresh()->category_id)->toBe($hosting->id);

    // Paying the open due now files the ledger entry under the new category.
    app(RecordPayment::class)->handle($owner, $open, new PaymentInput(500_000, $account->id, PaymentMethod::Cash, '2026-04-02', null, null, null, false));
    expect(AccountEntry::query()->latest('id')->value('category_id'))->toBe($tools->id);
});

test('a project is never listed as unscheduled, even when every milestone is paid', function () {
    roundNow();
    ['contract' => $contract, 'client' => $client, 'owner' => $owner] = projectWithMilestones();
    $account = Account::factory()->forOffice(Office::query()->findOrFail($client->office_id))->create(['opening_date' => '2026-01-01']);
    foreach (Due::query()->whereNotNull('milestone_id')->get() as $due) {
        app(RecordPayment::class)->handle($owner, $due, new PaymentInput($due->amount_due_cents, $account->id, PaymentMethod::Cash, '2026-04-02', null, null, null, false));
    }

    $this->actingAs($owner)->get(route('home', ['tab' => 'attention']))->assertInertia(fn (Assert $page) => $page->has('attention', 0));
    expect($contract->refresh()->status)->toBe(ContractStatus::Active);
});

test('a rebalance leaves canceled milestones out and shares the rest of the contract among the others', function () {
    roundNow();
    ['contract' => $contract, 'owner' => $owner] = projectWithMilestones();
    [$deposit, $beta, $launch] = $contract->milestones()->get()->all();
    Due::query()->where('milestone_id', $launch->id)->update(['status' => DueStatus::Canceled, 'canceled_reason' => 'Out of scope']);

    $rows = [
        ['id' => $deposit->id, 'label' => 'Deposit', 'mode' => 'percent', 'value' => 50],
        ['id' => $beta->id, 'label' => 'Beta', 'mode' => 'percent', 'value' => 50],
    ];

    $plan = collect(app(RebalanceMilestones::class)->handle($contract, $rows, apply: false))->keyBy('id');

    expect($plan[$deposit->id]['newCents'])->toBe(5_000_000)
        ->and($plan[$beta->id]['newCents'])->toBe(5_000_000)
        ->and($plan[$launch->id])->toMatchArray(['canceled' => true, 'locked' => true, 'newCents' => 3_000_000]);

    // A plan that still includes the canceled one is refused.
    expect(fn () => app(RebalanceMilestones::class)->handle($contract, [
        ...$rows,
        ['id' => $launch->id, 'label' => 'Launch', 'mode' => 'percent', 'value' => 0.001],
    ], apply: false))->toThrow(ValidationException::class);

    app(RebalanceMilestones::class)->handle($contract, $rows, apply: true);
    expect($deposit->refresh()->amount_cents)->toBe(5_000_000)->and($launch->refresh()->amount_cents)->toBe(3_000_000);
});
