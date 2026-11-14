<?php

use App\Actions\Dues\GenerateDues;
use App\Actions\Payments\RecordPayment;
use App\Actions\Payments\ReversePayment;
use App\Data\PaymentInput;
use App\Enums\CategoryKind;
use App\Enums\ContractStatus;
use App\Enums\ContractType;
use App\Enums\Direction;
use App\Enums\DueKind;
use App\Enums\PaymentMethod;
use App\Enums\Role;
use App\Models\Account;
use App\Models\AccountEntry;
use App\Models\Category;
use App\Models\Client;
use App\Models\Contract;
use App\Models\Due;
use App\Models\Office;
use App\Models\Supplier;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

afterEach(function () {
    CarbonImmutable::setTestNow();
});

/**
 * @return array<string, mixed>
 */
function costPayload(Supplier $supplier, Category $category, Office $office, array $overrides = []): array
{
    return [
        'type' => 'recurring_cost',
        'supplier_id' => $supplier->id,
        'category_id' => $category->id,
        'office_id' => $office->id,
        'amount_cents' => 1_500_000,
        'cycle' => 'monthly',
        'starts_on' => '2026-03-01',
        'ends_on' => null,
        'auto_renew' => true,
        'notes' => null,
        ...$overrides,
    ];
}

function costContract(array $overrides = []): Contract
{
    $office = $overrides['office'] ?? Office::factory()->create();
    unset($overrides['office']);

    return Contract::factory()->create([
        'type' => ContractType::RecurringCost,
        'direction' => Direction::Out,
        'client_id' => null,
        'supplier_id' => Supplier::factory()->create()->id,
        'category_id' => Category::named('Hosting')->id,
        'office_id' => $office->id,
        'cycle' => 'monthly',
        'starts_on' => '2026-01-10',
        ...$overrides,
    ]);
}

test('guests are redirected and account managers are refused on suppliers and categories', function () {
    $manager = User::factory()->accountManager()->create();
    $supplier = Supplier::factory()->create();

    $this->get(route('suppliers.index'))->assertRedirect(route('login'));
    $this->get(route('categories.index'))->assertRedirect(route('login'));

    $this->actingAs($manager);
    $this->get(route('suppliers.index'))->assertForbidden();
    $this->post(route('suppliers.store'), ['name' => 'X'])->assertForbidden();
    $this->patch(route('suppliers.update', $supplier), ['name' => 'Y'])->assertForbidden();
    $this->delete(route('suppliers.destroy', $supplier))->assertForbidden();
    $this->get(route('categories.index'))->assertForbidden();
    $this->post(route('categories.store'), ['name' => 'X', 'kind' => 'cost'])->assertForbidden();
});

test('owner and finance manage suppliers', function (Role $role) {
    $this->actingAsRole($role);

    $this->post(route('suppliers.store'), ['name' => 'Hosting SARL', 'contact' => 'Amel', 'email' => 'a@hosting.test'])
        ->assertSessionHasNoErrors();
    $supplier = Supplier::query()->where('name', 'Hosting SARL')->sole();

    $this->patch(route('suppliers.update', $supplier), ['name' => 'Hosting SPA'])->assertSessionHasNoErrors();
    expect($supplier->refresh()->name)->toBe('Hosting SPA');

    $this->get(route('suppliers.index', ['q' => 'spa']))
        ->assertInertia(fn (Assert $page) => $page->component('suppliers/Index')->has('suppliers', 1)->where('suppliers.0.name', 'Hosting SPA'));
    $this->get(route('suppliers.index', ['q' => 'nothing']))
        ->assertInertia(fn (Assert $page) => $page->has('suppliers', 0));

    $this->delete(route('suppliers.destroy', $supplier))->assertSessionHas('success');
    expect(Supplier::query()->count())->toBe(0)->and(Supplier::withTrashed()->count())->toBe(1);
})->with([Role::Owner, Role::Finance]);

test('a supplier with a live contract or an open due cannot be deleted', function () {
    $this->actingAsRole(Role::Owner);
    $withContract = costContract();
    $office = Office::factory()->create();
    $withDue = Supplier::factory()->create();
    Due::factory()->forSupplier($withDue, $office)->create();

    $this->delete(route('suppliers.destroy', $withContract->supplier_id))->assertSessionHas('error');
    $this->delete(route('suppliers.destroy', $withDue))->assertSessionHas('error');

    expect(Supplier::query()->count())->toBe(2);
});

test('categories can be added and renamed but never change kind', function () {
    $this->actingAsRole(Role::Finance);

    $this->post(route('categories.store'), ['name' => 'Insurance', 'kind' => 'cost'])->assertSessionHasNoErrors();
    $this->post(route('categories.store'), ['name' => 'Insurance', 'kind' => 'cost'])->assertSessionHasErrors('name');
    // The same name is fine under the other kind.
    $this->post(route('categories.store'), ['name' => 'Insurance', 'kind' => 'income'])->assertSessionHasNoErrors();

    $category = Category::query()->where('name', 'Insurance')->where('kind', 'cost')->sole();

    $this->patch(route('categories.update', $category), ['name' => 'Insurance & fees'])->assertSessionHasNoErrors();
    $this->patch(route('categories.update', $category), ['name' => 'Insurance & fees', 'kind' => 'income'])->assertSessionHasErrors('kind');
    $this->patch(route('categories.update', $category), ['name' => 'Insurance & fees', 'active' => false])->assertSessionHasNoErrors();

    expect($category->refresh()->kind)->toBe(CategoryKind::Cost)
        ->and($category->active)->toBeFalse()
        ->and($category->name)->toBe('Insurance & fees');
});

test('a recurring cost needs a supplier and a category and is money out', function () {
    CarbonImmutable::setTestNow('2026-03-01 09:00:00');
    $owner = $this->actingAsRole(Role::Owner);
    $office = Office::factory()->create();
    $supplier = Supplier::factory()->create();
    $category = Category::named('Rent');

    $this->post(route('contracts.store'), costPayload($supplier, $category, $office, ['supplier_id' => null]))
        ->assertSessionHasErrors('supplier_id');
    $this->post(route('contracts.store'), costPayload($supplier, $category, $office, ['category_id' => null]))
        ->assertSessionHasErrors('category_id');
    // A client has no place on a cost, and an income category is not a cost category.
    $this->post(route('contracts.store'), costPayload($supplier, $category, $office, ['client_id' => 1]))
        ->assertSessionHasErrors('client_id');
    $income = Category::query()->where('kind', 'income')->firstOrFail();
    $this->post(route('contracts.store'), costPayload($supplier, $income, $office))->assertSessionHasErrors('category_id');

    $this->post(route('contracts.store'), costPayload($supplier, $category, $office))->assertSessionHasNoErrors();

    $contract = Contract::query()->sole();
    expect($contract->type)->toBe(ContractType::RecurringCost)
        ->and($contract->direction)->toBe(Direction::Out)
        ->and($contract->client_id)->toBeNull()
        ->and($contract->supplier_id)->toBe($supplier->id)
        ->and($contract->category_id)->toBe($category->id)
        ->and($contract->office_id)->toBe($office->id);
});

test('generation makes outgoing dues that carry the contract category', function () {
    CarbonImmutable::setTestNow('2026-03-01 09:00:00');
    $contract = costContract(['starts_on' => '2026-03-01']);

    app(GenerateDues::class)->forContract($contract);
    $due = Due::query()->sole();

    expect($due->direction)->toBe(Direction::Out)
        ->and($due->kind)->toBe(DueKind::Expense)
        ->and($due->client_id)->toBeNull()
        ->and($due->supplier_id)->toBe($contract->supplier_id)
        ->and($due->category_id)->toBe($contract->category_id)
        ->and($due->office_id)->toBe($contract->office_id);

    // The nightly run covers supplier contracts too, and stays idempotent.
    expect(app(GenerateDues::class)->handle()->created)->toBe(0);
});

test('account managers cannot create, see or open recurring costs', function () {
    $office = Office::factory()->create();
    $manager = User::factory()->accountManager()->forOffice($office->id)->create();
    $contract = costContract(['office' => $office]);
    $this->actingAs($manager);

    $this->post(route('contracts.store'), costPayload(Supplier::factory()->create(), Category::named('Rent'), $office))->assertForbidden();
    $this->get(route('contracts.show', $contract))->assertForbidden();
    $this->get(route('contracts.index'))->assertInertia(fn (Assert $page) => $page
        ->has('contracts', 0)
        // Projects are client work, so account managers may create them; costs they may not.
        ->where('types', [
            ['value' => 'saas_renewal', 'label' => 'SaaS renewal'],
            ['value' => 'custom_project', 'label' => 'Custom project'],
        ])
        ->where('suppliers', [])
        ->where('categories', []));
});

test('the type and supplier of an existing contract cannot change', function () {
    $owner = $this->actingAsRole(Role::Owner);
    $contract = costContract();
    $other = Supplier::factory()->create();

    $this->patch(route('contracts.update', $contract), costPayload($other, Category::named('Rent'), $contract->office))
        ->assertSessionHasErrors('supplier_id');

    $this->patch(route('contracts.update', $contract), [
        'type' => 'saas_renewal',
        'client_id' => Client::factory()->forOffice($contract->office)->create()->id,
        'amount_cents' => 100_000,
        'cycle' => 'monthly',
        'starts_on' => '2026-03-01',
        'auto_renew' => true,
        'office_id' => $contract->office_id,
    ])
        ->assertSessionHasErrors('type');

    expect($contract->refresh()->type)->toBe(ContractType::RecurringCost);
});

test('contracts can be searched by supplier name', function () {
    $this->actingAsRole(Role::Owner);
    costContract()->supplier->update(['name' => 'Namecheap']);
    costContract();

    $this->get(route('contracts.index', ['q' => 'namecheap']))
        ->assertInertia(fn (Assert $page) => $page->has('contracts', 1)->where('contracts.0.partyName', 'Namecheap'));
});

test('paying a cost files the ledger entry under its category, and so does the reversal', function () {
    CarbonImmutable::setTestNow('2026-03-05 09:00:00');
    $owner = User::factory()->owner()->create();
    $contract = costContract(['starts_on' => '2026-03-01']);
    $due = Due::factory()->forSupplier($contract->supplier, $contract->office)->create([
        'contract_id' => $contract->id,
        'category_id' => $contract->category_id,
        'amount_due_cents' => 800_000,
        'due_on' => '2026-03-01',
        'period' => '2026-03-01',
    ]);
    $account = Account::factory()->forOffice($contract->office)->create(['opening_balance_cents' => 5_000_000, 'opening_date' => '2026-01-01']);

    $payment = app(RecordPayment::class)->handle($owner, $due, new PaymentInput(800_000, $account->id, PaymentMethod::Cash, '2026-03-05', null, null, null, false));
    app(ReversePayment::class)->handle($owner, $payment, 'Paid twice');

    $entries = AccountEntry::query()->orderBy('id')->get();

    expect($entries)->toHaveCount(2)
        ->and($entries[0]->direction)->toBe(Direction::Out)
        ->and($entries[1]->direction)->toBe(Direction::In)
        ->and($entries->pluck('category_id')->unique()->all())->toBe([$contract->category_id])
        ->and($entries->pluck('is_payroll')->unique()->all())->toBe([false])
        ->and($account->balanceCents())->toBe(5_000_000);
});

test('Needs attention lists contracts that end within thirty days until they are renewed or closed', function () {
    CarbonImmutable::setTestNow('2026-03-11 10:00:00');
    $owner = User::factory()->owner()->create();
    $office = Office::factory()->create();
    $client = Client::factory()->forOffice($office)->create();
    $make = fn (array $attributes) => Contract::factory()->create([
        'client_id' => $client->id,
        'office_id' => $office->id,
        'cycle' => 'monthly',
        'starts_on' => '2026-01-15',
        'auto_renew' => true,
        ...$attributes,
    ]);

    $ending = $make(['ends_on' => '2026-04-05']);          // 25 days away: flagged
    $far = $make(['ends_on' => '2026-06-30']);              // renewed far out: not flagged
    $canceled = $make(['ends_on' => '2026-04-01', 'status' => ContractStatus::Canceled]);
    $endingCost = costContract(['ends_on' => '2026-04-09', 'starts_on' => '2026-01-20']);

    $tabs = collect($this->actingAs($owner)->get(route('home'))->viewData('page')['props']['tabs'])->keyBy('value');
    expect($tabs['attention']['count'])->toBe(2);

    $this->actingAs($owner)->get(route('home', ['tab' => 'attention']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('attention', 2)
            ->where('attention.0.id', $ending->id)
            ->where('attention.0.reason', 'Ends on 05 Apr 2026. Renew it, or let it end.')
            ->where('attention.1.id', $endingCost->id)
            ->where('attention.1.clientName', $endingCost->supplier->name));

    // Money-in only: the cost drops out.
    $this->actingAs($owner)->get(route('home', ['tab' => 'attention', 'direction' => 'in']))
        ->assertInertia(fn (Assert $page) => $page->has('attention', 1)->where('attention.0.id', $ending->id));

    // Renewing it (moving the end date out) clears the flag.
    $ending->update(['ends_on' => '2026-12-31']);
    $this->actingAs($owner)->get(route('home', ['tab' => 'attention']))
        ->assertInertia(fn (Assert $page) => $page->has('attention', 1)->where('attention.0.id', $endingCost->id));

    expect([$far->id, $canceled->id])->each->not->toBeNull();
});

test('outgoing dues show on triage as money to pay with the Pay action for owner and finance only', function () {
    CarbonImmutable::setTestNow('2026-03-11 10:00:00');
    $office = Office::factory()->create();
    $supplier = Supplier::factory()->create(['name' => 'Landlord']);
    Due::factory()->forSupplier($supplier, $office)->create(['due_on' => '2026-03-01', 'period' => '2026-03-01', 'amount_due_cents' => 4_500_000]);

    foreach ([Role::Owner, Role::Finance] as $role) {
        $response = $this->actingAs(User::factory()->create(['role' => $role]))->get(route('home'));
        $overdue = collect($response->viewData('page')['props']['tabs'])->firstWhere('value', 'overdue');

        expect($overdue['payCents'])->toBe(4_500_000)->and($overdue['collectCents'])->toBe(0);

        $response->assertInertia(fn (Assert $page) => $page
            ->where('dues.data.0.partyName', 'Landlord')
            ->where('dues.data.0.partyType', 'supplier')
            ->where('dues.data.0.direction', 'out')
            ->where('dues.data.0.abilities.pay', true)
            ->where('dues.data.0.abilities.remind', false));
    }

    $manager = User::factory()->accountManager()->forOffice($office->id)->create();
    $this->actingAs($manager)->get(route('home'))->assertInertia(fn (Assert $page) => $page->has('dues.data', 0));
});
