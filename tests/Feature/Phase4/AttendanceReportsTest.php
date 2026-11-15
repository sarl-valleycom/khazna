<?php

use App\Attendance\MonthlySummary;
use App\Enums\AttendanceReviewStatus;
use App\Enums\AttendanceStatus;
use App\Enums\Role;
use App\Models\AttendanceBreak;
use App\Models\AttendanceSession;
use App\Models\AttendanceSetting;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\LeaveRequest;
use App\Models\Office;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use Smalot\PdfParser\Parser;

uses(RefreshDatabase::class);

afterEach(function () {
    CarbonImmutable::setTestNow();
});

function attDec(): void
{
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-12-05 10:00:00', 'Africa/Algiers'));
}

function attEmployee(array $attributes = []): Employee
{
    return Employee::factory()->forOffice(Office::factory()->create(['name' => 'Algiers HQ']))->create(['expected_minutes_per_day' => 480, ...$attributes]);
}

/** @param list<string> $dates */
function attWork(Employee $employee, array $dates, ?string $report = null): void
{
    foreach ($dates as $date) {
        AttendanceSession::factory()->create([
            'employee_id' => $employee->id,
            'started_at' => CarbonImmutable::parse("{$date} 09:00:00", 'Africa/Algiers')->utc(),
            'ended_at' => CarbonImmutable::parse("{$date} 17:00:00", 'Africa/Algiers')->utc(),
            'status' => AttendanceStatus::Closed,
            'review_status' => AttendanceReviewStatus::Ok,
            'report' => $report,
        ]);
    }
}

/** @return list<string> Every Sunday to Thursday of November 2026. */
function attNovemberDays(): array
{
    $dates = [];

    for ($day = CarbonImmutable::parse('2026-11-01'); $day->month === 11; $day = $day->addDay()) {
        if ($day->dayOfWeek <= 4) {
            $dates[] = $day->toDateString();
        }
    }

    return $dates;
}

/** @return array<string, list<list<mixed>>> */
function attBook(TestResponse $response): array
{
    $response->assertOk();
    $book = IOFactory::load($response->baseResponse->getFile()->getPathname());
    $sheets = [];

    foreach ($book->getSheetNames() as $name) {
        $sheets[$name] = $book->getSheetByName($name)->toArray(null, false, false, false);
    }

    return $sheets;
}

function attPdf(TestResponse $response): string
{
    $response->assertOk();

    expect($response->headers->get('content-type'))->toBe('application/pdf');

    return Normalizer::normalize((new Parser)->parseContent((string) $response->getContent())->getText(), Normalizer::FORM_KC);
}

test('guests are sent to the login and only the owner opens the attendance reports', function () {
    $employee = attEmployee();

    foreach (['attendance.summary.export' => [], 'attendance.summary.employee.export' => [$employee], 'attendance.summary.employee.pdf' => [$employee]] as $route => $args) {
        $this->get(route($route, $args))->assertRedirect(route('login'));
    }

    foreach ([Role::Finance, Role::AccountManager] as $role) {
        $this->actingAsRole($role);

        foreach (['attendance.summary.export' => [], 'attendance.summary.employee.export' => [$employee], 'attendance.summary.employee.pdf' => [$employee]] as $route => $args) {
            $this->get(route($route, $args))->assertForbidden();
        }
    }
});

test('the team workbook has one row per employee with the hand-computed month', function () {
    attDec();
    $this->actingAsRole(Role::Owner);
    $employee = attEmployee(['name' => 'Karim']);
    attWork($employee, array_slice(attNovemberDays(), 0, 21));

    $sheets = attBook($this->get(route('attendance.summary.export', ['month' => '2026-11'])));
    $rows = collect($sheets['November 2026'])->slice(1)->values();

    // 22 work days x 480 = 10 560 expected; 21 worked = 10 080; 480 to make up.
    expect($sheets['November 2026'][0][0])->toBe('Employee')
        ->and($rows)->toHaveCount(1)
        ->and($rows[0][0])->toBe('Karim')
        ->and($rows[0][1])->toBe('Algiers HQ')
        ->and($rows[0][2])->toBe(10080)
        ->and($rows[0][3])->toBe('168h 00m')
        ->and($rows[0][4])->toBe(10560)
        ->and($rows[0][5])->toBe('176h 00m')
        ->and($rows[0][6])->toBe(480)
        ->and($rows[0][7])->toBe('8h 00m')
        ->and($rows[0][10])->toBe(21);
});

test('the file lists the same people and numbers as the screen', function () {
    attDec();
    $this->actingAsRole(Role::Owner);
    $one = attEmployee(['name' => 'Alpha']);
    $two = attEmployee(['name' => 'Beta']);
    attEmployee(['name' => 'Gone', 'status' => 'inactive', 'left_on' => '2025-01-01']);
    attWork($one, array_slice(attNovemberDays(), 0, 5));
    attWork($two, array_slice(attNovemberDays(), 0, 3));

    $screen = null;
    $this->get(route('attendance.summary.index', ['month' => '2026-11']))->assertInertia(function (Assert $page) use (&$screen): void {
        $screen = $page->toArray()['props']['rows'];
    });

    $rows = collect(attBook($this->get(route('attendance.summary.export', ['month' => '2026-11'])))['November 2026'])->slice(1)->values();

    expect($rows->pluck(0)->all())->toBe(collect($screen)->pluck('name')->all())
        ->and($rows->pluck(2)->all())->toBe(collect($screen)->pluck('workedMinutes')->all())
        ->and($rows->pluck(4)->all())->toBe(collect($screen)->pluck('expectedMinutes')->all());
});

test('one employee workbook has a summary, every day of the month and the sessions', function () {
    attDec();
    $this->actingAsRole(Role::Owner);
    $employee = attEmployee(['name' => 'Karim']);
    $other = attEmployee(['name' => 'Someone else']);
    Holiday::factory()->create(['date' => '2026-11-01', 'label' => 'Revolution Day']);
    LeaveRequest::factory()->approved()->create(['employee_id' => $employee->id, 'from' => '2026-11-02', 'to' => '2026-11-02']);
    attWork($employee, ['2026-11-03', '2026-11-04'], 'Wrote the report');
    attWork($other, ['2026-11-03']);

    $sheets = attBook($this->get(route('attendance.summary.employee.export', ['employee' => $employee, 'month' => '2026-11'])));
    $summary = collect($sheets['Summary'])->pluck(1, 0);
    $days = collect($sheets['Days'])->slice(1)->values();
    $sessions = collect($sheets['Sessions'])->slice(1)->values();
    $byDate = $days->mapWithKeys(fn (array $row): array => [Date::excelToDateTimeObject($row[0])->format('Y-m-d') => $row]);

    expect(array_keys($sheets))->toBe(['Summary', 'Days', 'Sessions'])
        ->and($summary['Employee'])->toBe('Karim')
        ->and($summary['Month'])->toBe('November 2026')
        ->and($summary['Worked (min)'])->toBe(960)
        // 22 work days - 1 holiday - 1 leave day = 20 x 480.
        ->and($summary['Expected (min)'])->toBe(9600)
        ->and($summary['Days off (holidays and leave)'])->toBe(2)
        ->and($days)->toHaveCount(30)
        ->and($byDate['2026-11-01'][5])->toBe('Holiday')
        ->and($byDate['2026-11-01'][2])->toBe(0)
        ->and($byDate['2026-11-02'][5])->toBe('Leave')
        ->and($byDate['2026-11-03'][2])->toBe(480)
        ->and($byDate['2026-11-03'][3])->toBe(480)
        ->and($byDate['2026-11-03'][4])->toBe(0)
        // Friday 6 and Saturday 7 are rest days.
        ->and($byDate['2026-11-06'][5])->toBe('Rest')
        ->and($sessions)->toHaveCount(2)
        ->and($sessions->pluck(1)->unique()->all())->toBe(['Karim']);
});

test('the PDF report shows the summary, the days and the sessions with their reports', function () {
    attDec();
    $this->actingAsRole(Role::Owner);
    $employee = attEmployee(['name' => 'Karim Benali']);
    attWork($employee, array_slice(attNovemberDays(), 0, 21), 'Finished the audit');

    $text = attPdf($this->get(route('attendance.summary.employee.pdf', ['employee' => $employee, 'month' => '2026-11'])));

    expect($text)->toContain('Attendance report')
        ->and($text)->toContain('Karim Benali')
        ->and($text)->toContain('November 2026')
        ->and($text)->toContain('Hours worked')
        ->and($text)->toContain('168h 00m')
        ->and($text)->toContain('176h 00m')
        ->and($text)->toContain('To make up')
        ->and($text)->toContain('8h 00m')
        ->and($text)->toContain('Finished the audit')
        ->and($text)->toContain('Salary does not depend on them');
});

test('a bad or future month falls back to the current month', function () {
    attDec();
    $this->actingAsRole(Role::Owner);
    $employee = attEmployee();

    foreach (['nonsense', '2030-01'] as $month) {
        expect(attBook($this->get(route('attendance.summary.export', ['month' => $month]))))->toHaveKey('December 2026');
        expect(attPdf($this->get(route('attendance.summary.employee.pdf', ['employee' => $employee, 'month' => $month]))))->toContain('December 2026');
    }
});

test('an Arabic employee name gets ASCII file names', function () {
    attDec();
    $this->actingAsRole(Role::Owner);
    $employee = attEmployee(['name' => 'محمد بن عبد الله']);

    foreach (['attendance.summary.employee.export', 'attendance.summary.employee.pdf'] as $route) {
        $response = $this->get(route($route, ['employee' => $employee, 'month' => '2026-11']));
        $response->assertOk();

        expect($response->headers->get('content-disposition'))->toMatch('/filename=["\']?khazna-attendance-[\x20-\x7e]+-2026-11\.(xlsx|pdf)/');
    }
});

test('the day by day view adds up to the monthly totals', function () {
    attDec();
    $employee = attEmployee();
    Holiday::factory()->create(['date' => '2026-11-10', 'label' => 'A holiday']);
    attWork($employee, ['2026-11-01', '2026-11-02', '2026-11-03']);

    $month = CarbonImmutable::parse('2026-11-10', 'Africa/Algiers');
    $summary = app(MonthlySummary::class);
    $days = $summary->daily($employee, $month);
    $total = $summary->for($employee, $month);

    expect(array_sum(array_column($days, 'expected')))->toBe($total['expected'])
        ->and(array_sum(array_column($days, 'worked')))->toBe($total['worked']);
});

test('the sessions sheet adds up to the summary when two sessions share one day and one allowance', function () {
    attDec();
    $this->actingAsRole(Role::Owner);
    AttendanceSetting::current()->update(['paid_break_minutes' => 30]);
    $employee = attEmployee(['name' => 'Karim']);

    foreach ([['08:00:00', '12:00:00', '10:00:00', '10:45:00'], ['13:00:00', '17:00:00', '15:00:00', '15:45:00']] as [$start, $end, $breakStart, $breakEnd]) {
        $session = AttendanceSession::factory()->create([
            'employee_id' => $employee->id,
            'started_at' => CarbonImmutable::parse("2026-11-03 {$start}", 'Africa/Algiers')->utc(),
            'ended_at' => CarbonImmutable::parse("2026-11-03 {$end}", 'Africa/Algiers')->utc(),
            'status' => AttendanceStatus::Closed,
            'review_status' => AttendanceReviewStatus::Ok,
        ]);
        AttendanceBreak::factory()->create([
            'session_id' => $session->id,
            'started_at' => CarbonImmutable::parse("2026-11-03 {$breakStart}", 'Africa/Algiers')->utc(),
            'ended_at' => CarbonImmutable::parse("2026-11-03 {$breakEnd}", 'Africa/Algiers')->utc(),
        ]);
    }

    $sheets = attBook($this->get(route('attendance.summary.employee.export', ['employee' => $employee, 'month' => '2026-11'])));
    $summary = collect($sheets['Summary'])->pluck(1, 0);
    $sessions = collect($sheets['Sessions'])->slice(1)->values();

    // 8h gross, two 45 minute breaks (90), one 30 minute allowance for the whole day: 60 unpaid. 480 - 60 = 420.
    expect($summary['Worked (min)'])->toBe(420)
        ->and($sessions->sum(9))->toBe(420);
});

test('the sessions export asks for the break allowance the same number of times however many rows', function () {
    attDec();
    $this->actingAsRole(Role::Owner);
    $employee = attEmployee();

    $settingsQueries = function () use ($employee): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get(route('attendance.sessions.export', ['employee' => $employee->id]))->assertOk();
        DB::disableQueryLog();

        return collect(DB::getQueryLog())->filter(fn (array $query): bool => str_contains($query['query'], 'attendance_settings'))->count();
    };

    attWork($employee, array_slice(attNovemberDays(), 0, 2));
    $settingsQueries(); // the very first call creates the settings row
    $few = $settingsQueries();

    attWork($employee, array_slice(attNovemberDays(), 2, 18));
    $many = $settingsQueries();

    expect($many)->toBe($few)->and($many)->toBe(1);
});

test('nobody is listed for a month before they were hired', function () {
    attDec();
    $this->actingAsRole(Role::Owner);
    $early = attEmployee(['name' => 'Already here', 'hire_date' => '2026-01-01']);
    attEmployee(['name' => 'Starts in December', 'hire_date' => '2026-12-01']);

    $screen = null;
    $this->get(route('attendance.summary.index', ['month' => '2026-10']))->assertInertia(function (Assert $page) use (&$screen): void {
        $screen = collect($page->toArray()['props']['rows'])->pluck('name')->all();
    });
    $file = collect(attBook($this->get(route('attendance.summary.export', ['month' => '2026-10'])))['October 2026'])->slice(1)->pluck(0)->all();

    expect($screen)->toBe([$early->name])
        ->and($file)->toBe([$early->name]);

    // In December both are listed.
    $december = collect(attBook($this->get(route('attendance.summary.export', ['month' => '2026-12'])))['December 2026'])->slice(1)->pluck(0)->all();

    expect($december)->toHaveCount(2);
});
