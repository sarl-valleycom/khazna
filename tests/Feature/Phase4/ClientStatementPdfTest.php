<?php

use App\Enums\DueStatus;
use App\Enums\PaymentMethod;
use App\Enums\Role;
use App\Models\Account;
use App\Models\Client;
use App\Models\Due;
use App\Models\Office;
use App\Models\Payment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Smalot\PdfParser\Parser;

uses(RefreshDatabase::class);

afterEach(function () {
    CarbonImmutable::setTestNow();
});

function pdfText(TestResponse $response): string
{
    $response->assertOk();

    expect($response->headers->get('content-type'))->toBe('application/pdf')
        ->and(substr((string) $response->getContent(), 0, 4))->toBe('%PDF');

    return (new Parser)->parseContent((string) $response->getContent())->getText();
}

function statementClient(array $attributes = []): Client
{
    return Client::factory()->create(['office_id' => Office::factory()->create()->id, ...$attributes]);
}

function statementDue(Client $client, string $dueOn, int $amount, int $paid = 0, array $attributes = []): Due
{
    return Due::factory()->forClient($client)->create([
        'due_on' => $dueOn,
        'period' => $dueOn,
        'amount_due_cents' => $amount,
        'amount_paid_cents' => $paid,
        'status' => $paid >= $amount ? DueStatus::Paid : ($paid > 0 ? DueStatus::Partial : DueStatus::Pending),
        ...$attributes,
    ]);
}

function statementPayment(Due $due, string $paidOn, int $amount, array $attributes = []): Payment
{
    return Payment::factory()->create([
        'due_id' => $due->id,
        'account_id' => Account::factory()->create(['office_id' => $due->office_id])->id,
        'amount_cents' => $amount,
        'paid_on' => $paidOn,
        'method' => PaymentMethod::Ccp,
        ...$attributes,
    ]);
}

test('guests are sent to the login', function () {
    $client = statementClient();

    $this->get(route('clients.statement', $client))->assertRedirect(route('login'));
});

test('a statement lists the dues and payments of the period with hand-computed totals', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-18 12:00:00', 'Africa/Algiers'));
    $this->actingAsRole(Role::Owner);
    $client = statementClient(['name' => 'Amal Trading']);

    // In the period: 24 000.00 due with 10 000.50 paid, and 6 000.00 unpaid.
    $first = statementDue($client, '2026-11-05', 2400000, 1000050, ['description' => 'Hosting plan']);
    statementDue($client, '2026-11-20', 600000, 0, ['description' => 'Support']);
    // Outside the period but still owed: 1 500.00.
    statementDue($client, '2026-12-10', 150000, 0, ['description' => 'December plan']);
    // A canceled due is never shown.
    statementDue($client, '2026-11-12', 999900, 0, ['description' => 'Canceled thing', 'status' => DueStatus::Canceled]);

    statementPayment($first, '2026-11-15', 1000050, ['reference' => 'CCP-7788']);
    // A reversed payment is never shown or counted.
    statementPayment($first, '2026-11-16', 777700, ['reversed_at' => now(), 'reversal_reason' => 'Bounced']);

    $text = pdfText($this->get(route('clients.statement', ['client' => $client, 'from' => '2026-11-01', 'to' => '2026-11-30'])));

    expect($text)->toContain('Client statement')
        ->and($text)->toContain('Amal Trading')
        ->and($text)->toContain('Hosting plan')
        ->and($text)->toContain('Support')
        ->and($text)->not->toContain('December plan')
        ->and($text)->not->toContain('Canceled thing')
        // Dues in the period: 24 000.00 + 6 000.00 = 30 000.00; paid 10 000.50; remaining 19 999.50.
        ->and($text)->toContain('30,000.00 DA')
        ->and($text)->toContain('10,000.50 DA')
        ->and($text)->toContain('19,999.50 DA')
        ->and($text)->toContain('CCP-7788')
        ->and($text)->not->toContain('7,777.00')
        // Outstanding overall: 19 999.50 + 1 500.00 = 21 499.50.
        ->and($text)->toContain('21,499.50 DA');
});

test('the default period is the current month', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-18 12:00:00', 'Africa/Algiers'));
    $this->actingAsRole(Role::Owner);
    $client = statementClient();
    statementDue($client, '2026-11-25', 100000, 0, ['description' => 'In November']);
    statementDue($client, '2026-10-25', 100000, 0, ['description' => 'In October']);

    $text = pdfText($this->get(route('clients.statement', $client)));

    expect($text)->toContain('In November')
        ->and($text)->not->toContain('In October');
});

test('a statement for an Arabic client name is produced and readable', function () {
    $this->actingAsRole(Role::Owner);
    $client = statementClient(['name' => 'شركة الأمل للتجارة', 'contact' => 'محمد بن عبد الله', 'phone' => '0555123456']);

    $response = $this->get(route('clients.statement', $client));
    $text = pdfText($response);

    // The PDF stores Arabic as shaped glyphs in visual order; folded back to plain letters the
    // name is there (read right to left, so its first word comes out reversed).
    $folded = Normalizer::normalize($text, Normalizer::FORM_KC);

    expect($folded)->toContain('ةكرش')
        // The phone number keeps its digit groups in order thanks to the bdi wrapper.
        ->and($text)->toContain('Phone: 0555 12 34 56')
        // A file name is plain ASCII even for an Arabic client.
        ->and($response->headers->get('content-disposition'))->toMatch('/^inline; filename="khazna-statement-[\x20-\x7e]+\.pdf"$/');
});

test('a bad or reversed period is handled, and a huge one is refused', function () {
    $this->actingAsRole(Role::Owner);
    $client = statementClient();

    // Reversed dates are swapped, a nonsense date falls back to the default.
    $this->get(route('clients.statement', ['client' => $client, 'from' => '2026-11-30', 'to' => '2026-11-01']))->assertOk();
    $this->get(route('clients.statement', ['client' => $client, 'from' => 'nonsense', 'to' => '2026-13-45']))->assertOk();
    $this->get(route('clients.statement', ['client' => $client, 'from' => '2000-01-01', 'to' => '2026-11-30']))->assertStatus(422);
});

test('an account manager can only print the statement of their own client', function () {
    $manager = $this->actingAsRole(Role::AccountManager);
    $mine = statementClient(['account_manager_id' => $manager->id]);
    $other = statementClient(['account_manager_id' => User::factory()->accountManager()->create()->id]);

    $this->get(route('clients.statement', $mine))->assertOk();
    $this->get(route('clients.statement', $other))->assertForbidden();
});

test('the statement never shows another client dues or payments', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-18 12:00:00', 'Africa/Algiers'));
    $this->actingAsRole(Role::Owner);
    $client = statementClient(['name' => 'First client']);
    $other = statementClient(['name' => 'Second client']);
    statementDue($other, '2026-11-10', 500000, 0, ['description' => 'Belongs to the other']);
    statementPayment(statementDue($other, '2026-11-11', 500000, 500000, ['description' => 'Other paid']), '2026-11-12', 500000, ['reference' => 'OTHER-REF']);

    $text = pdfText($this->get(route('clients.statement', $client)));

    expect($text)->not->toContain('Belongs to the other')
        ->and($text)->not->toContain('OTHER-REF')
        ->and($text)->not->toContain('Second client');
});
