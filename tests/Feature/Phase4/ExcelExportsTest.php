<?php

use App\Enums\AttendanceReviewStatus;
use App\Enums\AttendanceStatus;
use App\Enums\ContractStatus;
use App\Enums\DueStatus;
use App\Enums\Role;
use App\Models\AttendanceSession;
use App\Models\Client;
use App\Models\Contract;
use App\Models\Due;
use App\Models\Employee;
use App\Models\Office;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

uses(RefreshDatabase::class);

afterEach(function () {
    CarbonImmutable::setTestNow();
});

function exportSheet(TestResponse $response): Worksheet
{
    $response->assertOk();

    return IOFactory::load($response->baseResponse->getFile()->getPathname())->getActiveSheet();
}

/**
 * The data rows (without the header) as plain values, in file order.
 *
 * @return list<list<mixed>>
 */
function exportRows(Worksheet $sheet): array
{
    $rows = $sheet->toArray(null, false, false, false);

    return array_values(array_slice($rows, 1));
}

function exportClient(array $attributes = []): Client
{
    return Client::factory()->create($attributes);
}

test('guests are sent to the login for every export', function () {
    foreach (['clients.export', 'contracts.export', 'dues.export', 'attendance.sessions.export'] as $route) {
        $this->get(route($route))->assertRedirect(route('login'));
    }
});

test('the clients export has a bold frozen header and one row per client', function () {
    $this->actingAsRole(Role::Owner);
    $office = Office::factory()->create(['name' => 'Algiers HQ']);
    exportClient(['name' => 'Amal Trading', 'phone' => '0555123456', 'office_id' => $office->id, 'notes' => 'VIP']);

    $response = $this->get(route('clients.export'));
    $sheet = exportSheet($response);

    expect($sheet->getTitle())->toBe('Clients')
        ->and($sheet->getCell('A1')->getValue())->toBe('ID')
        ->and($sheet->getCell('B1')->getValue())->toBe('Name')
        ->and($sheet->getStyle('A1')->getFont()->getBold())->toBeTrue()
        ->and($sheet->getFreezePane())->toBe('A2')
        ->and(exportRows($sheet))->toHaveCount(1)
        ->and(exportRows($sheet)[0][1])->toBe('Amal Trading')
        ->and(exportRows($sheet)[0][8])->toBe('Algiers HQ');

    expect($response->headers->get('content-disposition'))->toContain('khazna-clients-');
});

test('a phone number keeps its leading zero', function () {
    $this->actingAsRole(Role::Owner);
    exportClient(['phone' => '0555123456']);

    $sheet = exportSheet($this->get(route('clients.export')));

    expect($sheet->getCell('D2')->getValue())->toBe('0555123456')
        ->and($sheet->getCell('D2')->getDataType())->toBe('s');
});

test('text that looks like a formula is stored as text, never run', function () {
    $this->actingAsRole(Role::Owner);
    exportClient(['name' => '=HYPERLINK("http://evil.test","Click")', 'notes' => '+1+1', 'address' => '@SUM(1,1)']);

    $sheet = exportSheet($this->get(route('clients.export')));

    expect($sheet->getCell('B2')->getDataType())->toBe('s')
        ->and($sheet->getCell('B2')->getValue())->toBe('=HYPERLINK("http://evil.test","Click")')
        ->and($sheet->getCell('G2')->getDataType())->toBe('s')
        ->and($sheet->getCell('F2')->getDataType())->toBe('s');
});

test('the export holds exactly what the filtered screen shows, not just one page', function () {
    $this->actingAsRole(Role::Owner);

    foreach (range(1, 12) as $number) {
        exportClient(['name' => 'Alpha '.$number]);
    }
    exportClient(['name' => 'Beta only']);

    $filters = ['q' => 'Alpha', 'per_page' => 10];

    // The screen shows 10 of 12 on its first page and says 12 in total.
    $this->get(route('clients.index', $filters))
        ->assertInertia(fn ($page) => $page->where('total', 12)->has('clients', 10));

    $rows = exportRows(exportSheet($this->get(route('clients.export', $filters))));

    expect($rows)->toHaveCount(12)
        ->and(collect($rows)->every(fn (array $row): bool => str_starts_with($row[1], 'Alpha')))->toBeTrue();
});

test('a chunked export never skips or repeats a row when names tie', function () {
    $this->actingAsRole(Role::Owner);
    config(['excel.exports.chunk_size' => 3]);

    foreach (range(1, 10) as $number) {
        exportClient(['name' => 'Same name']);
    }

    $ids = collect(exportRows(exportSheet($this->get(route('clients.export')))))->pluck(0);

    expect($ids)->toHaveCount(10)
        ->and($ids->unique())->toHaveCount(10);
});

test('an account manager exports only their own clients', function () {
    $manager = $this->actingAsRole(Role::AccountManager);
    $other = User::factory()->accountManager()->create();
    exportClient(['name' => 'Mine', 'account_manager_id' => $manager->id]);
    exportClient(['name' => 'Not mine', 'account_manager_id' => $other->id]);

    $rows = exportRows(exportSheet($this->get(route('clients.export'))));

    expect($rows)->toHaveCount(1)
        ->and($rows[0][1])->toBe('Mine');
});

test('the contracts export applies the status filter and writes money and dates as numbers', function () {
    $this->actingAsRole(Role::Owner);
    $client = exportClient(['name' => 'Amal Trading']);
    Contract::factory()->create([
        'client_id' => $client->id,
        'amount_cents' => 1250050,
        'starts_on' => '2026-03-15',
        'ends_on' => null,
        'status' => ContractStatus::Active,
    ]);
    Contract::factory()->create(['client_id' => $client->id, 'status' => ContractStatus::Canceled]);

    $sheet = exportSheet($this->get(route('contracts.export', ['status' => 'active'])));
    $rows = exportRows($sheet);

    expect($sheet->getTitle())->toBe('Contracts')
        ->and($rows)->toHaveCount(1)
        ->and($rows[0][1])->toBe('Amal Trading')
        ->and((float) $rows[0][5])->toBe(12500.5)
        ->and(Date::excelToDateTimeObject($rows[0][7])->format('Y-m-d'))->toBe('2026-03-15')
        ->and($rows[0][8])->toBeNull()
        ->and($sheet->getStyle('F2')->getNumberFormat()->getFormatCode())->toBe('#,##0.00')
        ->and($sheet->getStyle('H2')->getNumberFormat()->getFormatCode())->toBe('yyyy-mm-dd');
});

test('the dues export follows the chosen tab and writes the amounts in DA', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-18 12:00:00', 'Africa/Algiers'));
    $this->actingAsRole(Role::Owner);
    $client = exportClient(['name' => 'Amal Trading', 'office_id' => Office::factory()->create()->id]);
    Due::factory()->forClient($client)->create([
        'due_on' => '2026-11-10',
        'period' => '2026-11-10',
        'amount_due_cents' => 5000000,
        'amount_paid_cents' => 1000050,
        'status' => DueStatus::Partial,
    ]);
    Due::factory()->forClient($client)->create(['due_on' => '2026-11-25', 'period' => '2026-11-25']);

    $overdue = exportRows(exportSheet($this->get(route('dues.export', ['tab' => 'overdue']))));
    $month = exportRows(exportSheet($this->get(route('dues.export', ['tab' => 'month']))));

    expect($overdue)->toHaveCount(1)
        ->and($overdue[0][1])->toBe('Amal Trading')
        ->and((float) $overdue[0][9])->toBe(50000.0)
        ->and((float) $overdue[0][10])->toBe(10000.5)
        ->and((float) $overdue[0][11])->toBe(39999.5)
        ->and($overdue[0][13])->toBe('Overdue')
        ->and($month)->toHaveCount(1);
});

test('a finance user cannot export salary dues, and the owner can', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-18 12:00:00', 'Africa/Algiers'));
    $employee = Employee::factory()->forOffice(Office::factory()->create())->create(['name' => 'Karim']);
    Due::factory()->forEmployee($employee, '2026-10-01')->create(['due_on' => '2026-11-05']);
    Due::factory()->forClient(exportClient(['name' => 'Amal Trading', 'office_id' => Office::factory()->create()->id]))->create(['due_on' => '2026-11-05']);

    $this->actingAsRole(Role::Finance);
    $finance = exportRows(exportSheet($this->get(route('dues.export', ['tab' => 'overdue']))));

    expect(collect($finance)->pluck(1)->all())->toBe(['Amal Trading']);

    $this->actingAsRole(Role::Owner);
    $owner = exportRows(exportSheet($this->get(route('dues.export', ['tab' => 'overdue']))));

    expect(collect($owner)->pluck(1)->sort()->values()->all())->toBe(['Amal Trading', 'Karim']);
});

test('the needs-attention tab has no export', function () {
    $this->actingAsRole(Role::Owner);

    $this->get(route('dues.export', ['tab' => 'attention']))->assertNotFound();
});

test('the sessions export is owner only and carries the screen numbers', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-18 12:00:00', 'Africa/Algiers'));
    $employee = Employee::factory()->forOffice(Office::factory()->create())->create(['name' => 'Karim']);
    $other = Employee::factory()->forOffice(Office::factory()->create())->create(['name' => 'Sara']);
    AttendanceSession::factory()->create([
        'employee_id' => $employee->id,
        'started_at' => CarbonImmutable::parse('2026-11-16 09:03:00', 'Africa/Algiers')->utc(),
        'ended_at' => CarbonImmutable::parse('2026-11-16 17:10:00', 'Africa/Algiers')->utc(),
        'status' => AttendanceStatus::Closed,
        'review_status' => AttendanceReviewStatus::Ok,
        'report' => 'Finished the audit.',
    ]);
    AttendanceSession::factory()->create([
        'employee_id' => $other->id,
        'started_at' => CarbonImmutable::parse('2026-11-16 09:00:00', 'Africa/Algiers')->utc(),
        'ended_at' => CarbonImmutable::parse('2026-11-16 17:00:00', 'Africa/Algiers')->utc(),
    ]);

    $this->actingAsRole(Role::Finance);
    $this->get(route('attendance.sessions.export'))->assertForbidden();

    $this->actingAsRole(Role::Owner);
    $sheet = exportSheet($this->get(route('attendance.sessions.export', ['employee' => $employee->id])));
    $rows = exportRows($sheet);

    expect($sheet->getTitle())->toBe('Sessions')
        ->and($rows)->toHaveCount(1)
        ->and($rows[0][1])->toBe('Karim')
        ->and(Date::excelToDateTimeObject($rows[0][3])->format('Y-m-d'))->toBe('2026-11-16')
        ->and($rows[0][4])->toBe('09:03')
        ->and($rows[0][5])->toBe('17:10')
        ->and($rows[0][9])->toBe(487)
        ->and($rows[0][10])->toBe('8h 07m')
        ->and($rows[0][11])->toBe('Finished the audit.');
});

test('a zero amount is written as zero, not left as an empty cell', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-18 12:00:00', 'Africa/Algiers'));
    $this->actingAsRole(Role::Owner);
    Due::factory()->forClient(exportClient(['name' => 'Amal Trading', 'office_id' => Office::factory()->create()->id]))->create([
        'due_on' => '2026-11-10',
        'period' => '2026-11-10',
        'amount_due_cents' => 5000000,
        'amount_paid_cents' => 0,
    ]);

    $sheet = exportSheet($this->get(route('dues.export', ['tab' => 'overdue'])));

    // K is "Paid (DA)", M is "Credit (DA)": both are zero here and must still be cells with a 0 in them.
    expect($sheet->getCell('K2')->getValue())->toBe(0.0)
        ->and($sheet->getCell('K2')->getDataType())->toBe('n')
        ->and($sheet->getCell('M2')->getValue())->toBe(0.0);
});
