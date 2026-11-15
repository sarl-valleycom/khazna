<?php

use App\Attendance\HoursBalance;
use App\Enums\EmployeeStatus;
use App\Enums\LeaveStatus;
use App\Enums\LeaveType;
use App\Enums\Role;
use App\Models\Activity;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\LeaveRequest;
use App\Models\Office;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

afterEach(function () {
    CarbonImmutable::setTestNow();
});

function calendarEmployee(array $attributes = []): Employee
{
    return Employee::factory()->forOffice(Office::factory()->create())->create($attributes);
}

test('guests are sent to the login and only the owner can open holidays and leave', function () {
    $this->get(route('attendance.holidays.index'))->assertRedirect(route('login'));
    $this->get(route('attendance.leave.index'))->assertRedirect(route('login'));

    foreach ([Role::Finance, Role::AccountManager] as $role) {
        $this->actingAsRole($role);

        $this->get(route('attendance.holidays.index'))->assertForbidden();
        $this->get(route('attendance.leave.index'))->assertForbidden();
        $this->post(route('attendance.holidays.store'), ['date' => '2026-11-01', 'label' => 'Revolution Day'])->assertForbidden();
        $this->post(route('attendance.leave.store'), [])->assertForbidden();
    }

    expect(Holiday::query()->count())->toBe(0);
});

test('the owner adds a holiday and sees it in its year', function () {
    $this->actingAsRole(Role::Owner);

    $this->post(route('attendance.holidays.store'), ['date' => '2026-11-01', 'label' => '  Revolution Day '])
        ->assertStatus(303)
        ->assertSessionHasNoErrors();
    Holiday::factory()->create(['date' => '2027-01-12', 'label' => 'Yennayer']);

    expect(Holiday::query()->where('date', '2026-11-01')->sole()->label)->toBe('Revolution Day');

    $this->get(route('attendance.holidays.index', ['year' => 2026]))
        ->assertInertia(fn (Assert $page) => $page
            ->component('attendance/Holidays')
            ->where('year', 2026)
            ->has('holidays', 1)
            ->where('holidays.0.label', 'Revolution Day')
            ->where('holidays.0.weekday', 'Sunday'));
});

test('a holiday needs a real date and a name', function () {
    $this->actingAsRole(Role::Owner);

    $this->post(route('attendance.holidays.store'), ['date' => 'tomorrow', 'label' => ''])
        ->assertSessionHasErrors(['date', 'label']);
});

test('adding the same date again renames it, and a removed date comes back', function () {
    $this->actingAsRole(Role::Owner);

    $this->post(route('attendance.holidays.store'), ['date' => '2026-11-01', 'label' => 'First name']);
    $this->post(route('attendance.holidays.store'), ['date' => '2026-11-01', 'label' => 'Second name']);

    expect(Holiday::query()->count())->toBe(1)
        ->and(Holiday::query()->sole()->label)->toBe('Second name');

    $holiday = Holiday::query()->sole();
    $this->delete(route('attendance.holidays.destroy', $holiday), ['reason' => 'Entered by mistake'])->assertStatus(303);

    expect(Holiday::query()->count())->toBe(0);

    $this->post(route('attendance.holidays.store'), ['date' => '2026-11-01', 'label' => 'Back again'])->assertSessionHasNoErrors();

    expect(Holiday::query()->sole()->label)->toBe('Back again');
});

test('removing a holiday needs a reason and is audited with it', function () {
    $this->actingAsRole(Role::Owner);
    $holiday = Holiday::factory()->create(['date' => '2026-11-01']);

    $this->delete(route('attendance.holidays.destroy', $holiday), ['reason' => ''])->assertSessionHasErrors('reason');
    expect(Holiday::query()->count())->toBe(1);

    $this->delete(route('attendance.holidays.destroy', $holiday), ['reason' => 'Date was wrong'])->assertStatus(303);

    $activity = Activity::query()
        ->where('subject_type', Holiday::class)
        ->where('subject_id', $holiday->id)
        ->where('event', 'deleted')
        ->latest('id')
        ->firstOrFail();

    expect($activity->getProperty('reason'))->toBe('Date was wrong');
});

test('the owner records leave and it is approved at once', function () {
    $owner = $this->actingAsRole(Role::Owner);
    $employee = calendarEmployee();

    $this->post(route('attendance.leave.store'), [
        'employee_id' => $employee->id,
        'type' => 'sick',
        'from' => '2026-11-18',
        'to' => '2026-11-19',
        'reason' => 'Flu, phoned in',
    ])->assertStatus(303)->assertSessionHasNoErrors();

    $leave = LeaveRequest::query()->sole();

    expect($leave->status)->toBe(LeaveStatus::Approved)
        ->and($leave->type)->toBe(LeaveType::Sick)
        ->and($leave->decided_by)->toBe($owner->id)
        ->and($leave->decided_at)->not->toBeNull()
        ->and($leave->from->toDateString())->toBe('2026-11-18');

    $this->get(route('attendance.leave.index', ['year' => 2026]))
        ->assertInertia(fn (Assert $page) => $page
            ->component('attendance/Leave')
            ->has('leaves', 1)
            ->where('leaves.0.employeeName', $employee->name)
            // Wednesday 18 and Thursday 19 are both work days.
            ->where('leaves.0.workDays', 2));
});

test('leave must end on or after it starts, and overlapping leave is refused', function () {
    $this->actingAsRole(Role::Owner);
    $employee = calendarEmployee();

    $this->post(route('attendance.leave.store'), [
        'employee_id' => $employee->id, 'type' => 'paid', 'from' => '2026-11-19', 'to' => '2026-11-18',
    ])->assertSessionHasErrors('to');

    $this->post(route('attendance.leave.store'), [
        'employee_id' => $employee->id, 'type' => 'paid', 'from' => '2026-11-15', 'to' => '2026-11-17',
    ])->assertSessionHasNoErrors();

    $this->post(route('attendance.leave.store'), [
        'employee_id' => $employee->id, 'type' => 'unpaid', 'from' => '2026-11-17', 'to' => '2026-11-20',
    ])->assertSessionHasErrors('from');

    expect(LeaveRequest::query()->count())->toBe(1);
});

test('leave cannot be recorded for a deactivated employee', function () {
    $this->actingAsRole(Role::Owner);
    $employee = calendarEmployee(['status' => EmployeeStatus::Inactive]);

    $this->post(route('attendance.leave.store'), [
        'employee_id' => $employee->id, 'type' => 'paid', 'from' => '2026-11-15', 'to' => '2026-11-15',
    ])->assertSessionHasErrors('employee_id');

    expect(LeaveRequest::query()->count())->toBe(0);
});

test('cancelling leave needs a reason and is audited with it', function () {
    $this->actingAsRole(Role::Owner);
    $employee = calendarEmployee();
    $leave = LeaveRequest::factory()->approved()->create([
        'employee_id' => $employee->id,
        'from' => '2026-11-18',
        'to' => '2026-11-18',
    ]);

    $this->delete(route('attendance.leave.destroy', $leave), ['reason' => ''])->assertSessionHasErrors('reason');
    expect(LeaveRequest::query()->count())->toBe(1);

    $this->delete(route('attendance.leave.destroy', $leave), ['reason' => 'He came in after all'])->assertStatus(303);

    expect(LeaveRequest::query()->count())->toBe(0);

    $activity = Activity::query()
        ->where('subject_type', LeaveRequest::class)
        ->where('subject_id', $leave->id)
        ->where('event', 'deleted')
        ->latest('id')
        ->firstOrFail();

    expect($activity->getProperty('reason'))->toBe('He came in after all');
});

test('a holiday and a leave day change the expected hours exactly as counted by hand', function () {
    // Thursday 19 Nov 2026. The week runs Sunday 15 to Thursday 19.
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-19 12:00:00', 'Africa/Algiers'));
    $owner = $this->actingAsRole(Role::Owner);
    $employee = calendarEmployee(['expected_minutes_per_day' => 480]);
    $balance = app(HoursBalance::class);

    // Nothing entered: 5 work days this week, 15 since 1 Nov (3 weeks of Sunday to Thursday).
    expect($balance->for($employee)['week']['expected'])->toBe(5 * 480)
        ->and($balance->for($employee)['month']['expected'])->toBe(15 * 480);

    $this->post(route('attendance.holidays.store'), ['date' => '2026-11-17', 'label' => 'Test holiday']);
    $this->post(route('attendance.leave.store'), [
        'employee_id' => $employee->id, 'type' => 'paid', 'from' => '2026-11-18', 'to' => '2026-11-18',
    ]);

    // Sunday, Monday and Thursday remain: 3 x 480 = 1 440. The month loses the same two days: 13 x 480 = 6 240.
    expect($balance->for($employee)['week']['expected'])->toBe(1440)
        ->and($balance->for($employee)['month']['expected'])->toBe(6240);

    // Removing the holiday gives its day back.
    $this->delete(route('attendance.holidays.destroy', Holiday::query()->sole()), ['reason' => 'Wrong date']);

    expect($balance->for($employee)['week']['expected'])->toBe(4 * 480)
        ->and($owner->isOwner())->toBeTrue();
});

test('one leave lasts at most a year', function () {
    $this->actingAsRole(Role::Owner);
    $employee = calendarEmployee();

    // A typo in the year: 36 years of leave.
    $this->post(route('attendance.leave.store'), [
        'employee_id' => $employee->id, 'type' => 'paid', 'from' => '2026-01-01', 'to' => '2062-12-31',
    ])->assertSessionHasErrors('to');

    $this->post(route('attendance.leave.store'), [
        'employee_id' => $employee->id, 'type' => 'paid', 'from' => '9999-12-01', 'to' => '9999-12-31',
    ])->assertSessionHasNoErrors();

    // 366 days is the longest allowed (a leap year); 367 is refused.
    $this->post(route('attendance.leave.store'), [
        'employee_id' => $employee->id, 'type' => 'unpaid', 'from' => '2028-01-01', 'to' => '2028-12-31',
    ])->assertSessionHasNoErrors();
    $this->post(route('attendance.leave.store'), [
        'employee_id' => $employee->id, 'type' => 'unpaid', 'from' => '2030-01-01', 'to' => '2031-01-02',
    ])->assertSessionHasErrors('to');

    expect(LeaveRequest::query()->count())->toBe(2);
});

test('the leave page stays fast even for a leave that is already enormous', function () {
    $this->actingAsRole(Role::Owner);
    $employee = calendarEmployee();
    // Made before the limit existed, or straight in the database.
    LeaveRequest::factory()->approved()->create(['employee_id' => $employee->id, 'from' => '2026-01-01', 'to' => '2999-12-31']);

    $started = microtime(true);

    $this->get(route('attendance.leave.index', ['year' => 2026]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('leaves', 1)->where('leaves.0.workDays', fn ($days) => $days > 100000));

    expect(microtime(true) - $started)->toBeLessThan(5.0);
});

test('counting work days by weeks gives the same answer as counting day by day', function () {
    foreach ([null, [0, 1, 2, 3, 4], [1, 2, 3], [0, 6], [0, 1, 2, 3, 4, 5, 6], [3]] as $workDays) {
        $employee = Employee::factory()->forOffice(Office::factory()->create())->create(['work_days' => $workDays]);

        foreach ([['2026-11-01', '2026-11-01'], ['2026-11-01', '2026-11-07'], ['2026-11-04', '2026-11-18'], ['2026-02-27', '2026-04-03'], ['2026-01-01', '2028-06-30'], ['2026-11-09', '2026-11-08']] as [$from, $to]) {
            $naive = 0;

            for ($day = CarbonImmutable::parse($from); $day->lte(CarbonImmutable::parse($to)); $day = $day->addDay()) {
                if ($employee->worksOn($day)) {
                    $naive++;
                }
            }

            expect($employee->countWorkDays($from, $to))->toBe($naive);
        }
    }
});
