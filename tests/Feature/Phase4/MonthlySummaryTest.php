<?php

use App\Attendance\MonthlySummary;
use App\Enums\AttendanceReviewStatus;
use App\Enums\AttendanceStatus;
use App\Enums\EmployeeStatus;
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
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

afterEach(function () {
    CarbonImmutable::setTestNow();
});

function summaryAt(string $dateTime): CarbonImmutable
{
    return CarbonImmutable::parse($dateTime, 'Africa/Algiers');
}

/**
 * A closed 8 hour session (09:00 to 17:00) on each given date.
 *
 * @param  list<string>  $dates
 */
function workDaysFor(Employee $employee, array $dates, string $start = '09:00:00', string $end = '17:00:00'): void
{
    foreach ($dates as $date) {
        AttendanceSession::factory()->create([
            'employee_id' => $employee->id,
            'started_at' => summaryAt("{$date} {$start}")->utc(),
            'ended_at' => summaryAt("{$date} {$end}")->utc(),
            'status' => AttendanceStatus::Closed,
            'review_status' => AttendanceReviewStatus::Ok,
        ]);
    }
}

/**
 * Every Sunday to Thursday of a month, as dates.
 *
 * @return list<string>
 */
function sundayToThursday(string $month): array
{
    $dates = [];

    for ($day = CarbonImmutable::parse("{$month}-01"); $day->month === (int) substr($month, 5); $day = $day->addDay()) {
        if ($day->dayOfWeek <= 4) {
            $dates[] = $day->toDateString();
        }
    }

    return $dates;
}

function summaryEmployee(array $attributes = []): Employee
{
    return Employee::factory()->forOffice(Office::factory()->create())->create($attributes);
}

test('November 2026 has 22 expected days; 21 worked leaves 480 minutes to make up', function () {
    CarbonImmutable::setTestNow(summaryAt('2026-12-05 10:00:00'));
    $employee = summaryEmployee(['expected_minutes_per_day' => 480]);
    $days = sundayToThursday('2026-11');

    expect($days)->toHaveCount(22);

    workDaysFor($employee, array_slice($days, 0, 21));

    $summary = app(MonthlySummary::class)->for($employee, summaryAt('2026-11-10 00:00:00'));

    expect($summary['expected'])->toBe(22 * 480)
        ->and($summary['expected'])->toBe(10560)
        ->and($summary['worked'])->toBe(21 * 480)
        ->and($summary['worked'])->toBe(10080)
        ->and($summary['to_make_up'])->toBe(480)
        ->and($summary['extra'])->toBe(0)
        ->and($summary['days_worked'])->toBe(21)
        ->and($summary['days_off'])->toBe(0);
});

test('holidays and leave are not expected and are counted as days off', function () {
    CarbonImmutable::setTestNow(summaryAt('2026-12-05 10:00:00'));
    $employee = summaryEmployee(['expected_minutes_per_day' => 480]);

    Holiday::factory()->create(['date' => '2026-11-01', 'label' => 'Revolution Day']); // a Sunday
    Holiday::factory()->create(['date' => '2026-11-07', 'label' => 'On a Saturday']); // not a work day
    LeaveRequest::factory()->approved()->create([
        'employee_id' => $employee->id,
        'from' => '2026-11-09',
        'to' => '2026-11-11', // Monday to Wednesday
    ]);
    // Pending leave does not count.
    LeaveRequest::factory()->create(['employee_id' => $employee->id, 'from' => '2026-11-15', 'to' => '2026-11-15']);

    $summary = app(MonthlySummary::class)->for($employee, summaryAt('2026-11-10 00:00:00'));

    // 22 days - 1 holiday - 3 leave days = 18 days x 480.
    expect($summary['expected'])->toBe(18 * 480)
        ->and($summary['days_off'])->toBe(4);
});

test('the current month runs only through today, so it is not shown as a debt', function () {
    // Wednesday 18 Nov 2026, noon. Work days so far: 1-5, 8-12, 15-18 = 14 days.
    CarbonImmutable::setTestNow(summaryAt('2026-11-18 12:00:00'));
    $employee = summaryEmployee(['expected_minutes_per_day' => 480]);

    workDaysFor($employee, ['2026-11-01', '2026-11-02']);

    $summary = app(MonthlySummary::class)->for($employee, summaryAt('2026-11-18 12:00:00'));

    expect($summary['expected'])->toBe(14 * 480)
        ->and($summary['worked'])->toBe(2 * 480)
        ->and($summary['to_make_up'])->toBe(12 * 480);
});

test('extra time is reported as extra, never as a negative to make up', function () {
    CarbonImmutable::setTestNow(summaryAt('2026-12-05 10:00:00'));
    $employee = summaryEmployee(['expected_minutes_per_day' => 480]);

    workDaysFor($employee, sundayToThursday('2026-11'), '08:00:00', '18:00:00');

    $summary = app(MonthlySummary::class)->for($employee, summaryAt('2026-11-10 00:00:00'));

    expect($summary['worked'])->toBe(22 * 600)
        ->and($summary['extra'])->toBe(22 * 120)
        ->and($summary['to_make_up'])->toBe(0);
});

test('someone hired in the month is only expected from the day they started', function () {
    CarbonImmutable::setTestNow(summaryAt('2026-12-05 10:00:00'));
    // Hired Sunday 22 Nov: 22, 23, 24, 25, 26, 29, 30 = 7 work days.
    $employee = summaryEmployee(['hire_date' => '2026-11-22', 'expected_minutes_per_day' => 480]);

    expect(app(MonthlySummary::class)->for($employee, summaryAt('2026-11-10 00:00:00'))['expected'])->toBe(7 * 480);
});

test('someone who left is only expected until their last day', function () {
    CarbonImmutable::setTestNow(summaryAt('2026-12-05 10:00:00'));
    // Left Thursday 5 Nov: 1, 2, 3, 4, 5 = 5 work days.
    $employee = summaryEmployee(['left_on' => '2026-11-05', 'status' => EmployeeStatus::Inactive, 'expected_minutes_per_day' => 480]);

    expect(app(MonthlySummary::class)->for($employee, summaryAt('2026-11-10 00:00:00'))['expected'])->toBe(5 * 480);
});

test('the paid break allowance is applied per day', function () {
    CarbonImmutable::setTestNow(summaryAt('2026-12-05 10:00:00'));
    AttendanceSetting::current()->update(['paid_break_minutes' => 30]);
    $employee = summaryEmployee(['expected_minutes_per_day' => 480]);

    foreach (['2026-11-01', '2026-11-02'] as $date) {
        $session = AttendanceSession::factory()->create([
            'employee_id' => $employee->id,
            'started_at' => summaryAt("{$date} 09:00:00")->utc(),
            'ended_at' => summaryAt("{$date} 17:00:00")->utc(),
            'status' => AttendanceStatus::Closed,
            'review_status' => AttendanceReviewStatus::Ok,
        ]);
        AttendanceBreak::factory()->create([
            'session_id' => $session->id,
            'started_at' => summaryAt("{$date} 12:00:00")->utc(),
            'ended_at' => summaryAt("{$date} 12:45:00")->utc(),
        ]);
    }

    // 8h minus 15 unpaid minutes (45 - 30 paid), each day: 465 x 2.
    expect(app(MonthlySummary::class)->for($employee, summaryAt('2026-11-10 00:00:00'))['worked'])->toBe(930);
});

test('automatically closed sessions that nobody checked are flagged, not hidden', function () {
    CarbonImmutable::setTestNow(summaryAt('2026-12-05 10:00:00'));
    $employee = summaryEmployee();
    AttendanceSession::factory()->autoClosed()->create([
        'employee_id' => $employee->id,
        'started_at' => summaryAt('2026-11-02 09:00:00')->utc(),
        'ended_at' => summaryAt('2026-11-02 17:00:00')->utc(),
    ]);

    $summary = app(MonthlySummary::class)->for($employee, summaryAt('2026-11-10 00:00:00'));

    expect($summary['not_checked'])->toBe(1)
        ->and($summary['worked'])->toBe(480);
});

test('one employee sessions never count for another', function () {
    CarbonImmutable::setTestNow(summaryAt('2026-12-05 10:00:00'));
    $one = summaryEmployee();
    $two = summaryEmployee();
    workDaysFor($one, ['2026-11-01', '2026-11-02', '2026-11-03']);
    workDaysFor($two, ['2026-11-01']);

    $rows = app(MonthlySummary::class)->forMany([$one, $two], summaryAt('2026-11-10 00:00:00'));

    expect($rows[$one->id]['worked'])->toBe(3 * 480)
        ->and($rows[$two->id]['worked'])->toBe(480);
});

test('a future month is empty', function () {
    CarbonImmutable::setTestNow(summaryAt('2026-11-18 12:00:00'));
    $employee = summaryEmployee();

    expect(app(MonthlySummary::class)->for($employee, summaryAt('2027-02-10 00:00:00'))['expected'])->toBe(0);
});

test('only the owner opens the monthly summary', function () {
    $this->get(route('attendance.summary.index'))->assertRedirect(route('login'));

    foreach ([Role::Finance, Role::AccountManager] as $role) {
        $this->actingAsRole($role);
        $this->get(route('attendance.summary.index'))->assertForbidden();
    }
});

test('the page lists each active employee for the chosen month, with a link to their sessions', function () {
    CarbonImmutable::setTestNow(summaryAt('2026-12-05 10:00:00'));
    $this->actingAsRole(Role::Owner);
    $employee = summaryEmployee(['name' => 'Karim', 'expected_minutes_per_day' => 480]);
    summaryEmployee(['name' => 'Gone', 'status' => EmployeeStatus::Inactive, 'left_on' => '2025-01-01']);
    workDaysFor($employee, array_slice(sundayToThursday('2026-11'), 0, 21));

    $this->get(route('attendance.summary.index', ['month' => '2026-11']))
        ->assertInertia(fn (Assert $page) => $page
            ->component('attendance/Summary')
            ->where('month', '2026-11')
            ->where('monthLabel', 'November 2026')
            ->where('previousMonth', '2026-10')
            ->where('nextMonth', '2026-12')
            ->has('rows', 1)
            ->where('rows.0.name', 'Karim')
            ->where('rows.0.expectedFormatted', '176h 00m')
            ->where('rows.0.workedFormatted', '168h 00m')
            ->where('rows.0.toMakeUpFormatted', '8h 00m')
            ->where('rows.0.sessionsUrl', '/attendance/sessions?employee='.$employee->id.'&from=2026-11-01&to=2026-11-30'));
});

test('a bad or future month falls back to the current month', function () {
    CarbonImmutable::setTestNow(summaryAt('2026-12-05 10:00:00'));
    $this->actingAsRole(Role::Owner);

    foreach (['nonsense', '2030-01', '2026-13'] as $month) {
        $this->get(route('attendance.summary.index', ['month' => $month]))
            ->assertInertia(fn (Assert $page) => $page->where('month', '2026-12')->where('nextMonth', null));
    }
});

test('the whole team costs the same few queries however many people', function () {
    CarbonImmutable::setTestNow(summaryAt('2026-12-05 10:00:00'));
    $employees = collect(range(1, 2))->map(fn () => summaryEmployee());
    $employees->each(fn (Employee $employee) => workDaysFor($employee, ['2026-11-01', '2026-11-02']));
    $month = summaryAt('2026-11-10 00:00:00');

    $queries = function (iterable $team) use ($month): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        app(MonthlySummary::class)->forMany($team, $month);
        DB::disableQueryLog();

        return count(DB::getQueryLog());
    };

    $queries($employees); // warm the settings row
    $two = $queries($employees);

    $more = $employees->concat(collect(range(1, 8))->map(fn () => summaryEmployee()));

    expect($queries($more))->toBe($two);
});
