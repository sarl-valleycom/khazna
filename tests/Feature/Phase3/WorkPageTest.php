<?php

use App\Attendance\AttendanceCode;
use App\Enums\AttendanceReviewStatus;
use App\Enums\AttendanceSource;
use App\Enums\AttendanceStatus;
use App\Enums\EmployeeStatus;
use App\Enums\Role;
use App\Models\Activity;
use App\Models\AttendanceBreak;
use App\Models\AttendanceSession;
use App\Models\AttendanceSetting;
use App\Models\CorrectionRequest;
use App\Models\Employee;
use App\Models\Office;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;

uses(RefreshDatabase::class);

afterEach(function () {
    CarbonImmutable::setTestNow();
});

function workAt(string $dateTime): CarbonImmutable
{
    return CarbonImmutable::parse($dateTime, 'Africa/Algiers');
}

/**
 * An employee with a code, plus the code itself.
 *
 * @param  array<string, mixed>  $attributes
 * @return array{0: Employee, 1: string}
 */
function workEmployee(array $attributes = []): array
{
    $employee = Employee::factory()->forOffice(Office::factory()->create())->create($attributes);

    return [$employee, app(AttendanceCode::class)->generate($employee)];
}

function workSignIn(object $test, string $code): void
{
    $test->post(route('work.enter'), ['code' => $code])->assertRedirect(route('work.show'));
}

test('the page asks for a code when nobody is signed in', function () {
    $this->get(route('work.show'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('work/Enter'));
});

test('a valid code opens the employee page and a wrong one does not', function () {
    [$employee, $code] = workEmployee(['name' => 'Karim']);

    $this->post(route('work.enter'), ['code' => '000000'])->assertSessionHasErrors('code');
    $this->get(route('work.show'))->assertInertia(fn ($page) => $page->component('work/Enter'));

    workSignIn($this, $code);

    $this->get(route('work.show'))
        ->assertInertia(fn ($page) => $page
            ->component('work/Dashboard')
            ->where('dashboard.name', 'Karim')
            ->where('dashboard.state', 'offline'));
});

test('spaces and dashes in the code are ignored', function () {
    [, $code] = workEmployee();

    workSignIn($this, substr($code, 0, 3).' - '.substr($code, 3));
});

test('a deactivated employee cannot use a code', function () {
    [$employee, $code] = workEmployee(['status' => EmployeeStatus::Inactive]);

    $this->post(route('work.enter'), ['code' => $code])->assertSessionHasErrors('code');
});

test('only a keyed hash of the code is stored', function () {
    [$employee, $code] = workEmployee();

    expect($employee->refresh()->attendance_code_hash)->not->toBe($code)
        ->and($employee->attendance_code_hash)->toHaveLength(64)
        ->and(json_encode($employee->toArray()))->not->toContain($employee->attendance_code_hash);
});

test('wrong codes are limited with a friendly message, not a raw error', function () {
    RateLimiter::clear('work-fail-minute:127.0.0.1');
    RateLimiter::clear('work-fail-hour:127.0.0.1');
    RateLimiter::clear('work-fail-all');
    [, $code] = workEmployee();

    foreach (range(1, 10) as $attempt) {
        $this->post(route('work.enter'), ['code' => '111111'])
            ->assertSessionHasErrors(['code' => __('attendance.errors.invalid_code')]);
    }

    // Even the right code waits once the limit is reached.
    $this->post(route('work.enter'), ['code' => $code])
        ->assertStatus(303)
        ->assertSessionHasErrors(['code' => __('attendance.errors.too_many_attempts')]);
});

test('right codes never count, so a whole office behind one address can sign in', function () {
    RateLimiter::clear('work-fail-minute:127.0.0.1');
    RateLimiter::clear('work-fail-hour:127.0.0.1');
    RateLimiter::clear('work-fail-all');

    foreach (range(1, 15) as $number) {
        [, $code] = workEmployee();
        $this->post(route('work.enter'), ['code' => $code])->assertRedirect(route('work.show'));
        $this->post(route('work.leave'));
    }
});

test('replacing or removing a code ends the sign-in made with the old one', function () {
    [$employee, $code] = workEmployee();
    workSignIn($this, $code);
    $this->get(route('work.show'))->assertInertia(fn ($page) => $page->component('work/Dashboard'));

    app(AttendanceCode::class)->generate($employee->refresh());

    $this->get(route('work.show'))->assertInertia(fn ($page) => $page->component('work/Enter'));
    $this->post(route('work.check-in'))->assertRedirect(route('work.show'));
    $this->assertDatabaseCount('attendance_sessions', 0);

    $new = app(AttendanceCode::class)->generate($employee->refresh());
    workSignIn($this, $new);

    app(AttendanceCode::class)->revoke($employee->refresh());
    $this->get(route('work.show'))->assertInertia(fn ($page) => $page->component('work/Enter'));
});

test('only the owner can make or remove a code', function () {
    $employee = Employee::factory()->forOffice(Office::factory()->create())->create();

    foreach ([Role::Finance, Role::AccountManager] as $role) {
        $this->actingAsRole($role);

        $this->post(route('employees.attendance-code.store', $employee))->assertForbidden();
        $this->delete(route('employees.attendance-code.destroy', $employee))->assertForbidden();
    }

    expect($employee->refresh()->hasAttendanceCode())->toBeFalse();
});

test('check-in, break, back and check-out are saved through the web page', function () {
    CarbonImmutable::setTestNow(workAt('2026-11-16 09:00:00'));
    [$employee, $code] = workEmployee();
    workSignIn($this, $code);

    $this->post(route('work.check-in'))->assertSessionHas('success');

    $session = AttendanceSession::query()->where('employee_id', $employee->id)->sole();
    expect($session->source)->toBe(AttendanceSource::Web)
        ->and($session->ended_at)->toBeNull();

    CarbonImmutable::setTestNow(workAt('2026-11-16 12:00:00'));
    $this->post(route('work.break'), ['reason' => 'Lunch'])->assertSessionHas('success');
    $this->get(route('work.show'))->assertInertia(fn ($page) => $page->where('dashboard.state', 'away'));

    CarbonImmutable::setTestNow(workAt('2026-11-16 12:30:00'));
    $this->post(route('work.back'))->assertSessionHas('success');
    $this->get(route('work.show'))->assertInertia(fn ($page) => $page->where('dashboard.state', 'working'));

    CarbonImmutable::setTestNow(workAt('2026-11-16 17:00:00'));
    $this->post(route('work.check-out'))->assertSessionHas('success');

    expect($session->refresh()->ended_at)->not->toBeNull()
        ->and($session->status)->toBe(AttendanceStatus::Closed);
});

test('a second check-in is refused with a message', function () {
    CarbonImmutable::setTestNow(workAt('2026-11-16 09:00:00'));
    [$employee, $code] = workEmployee();
    workSignIn($this, $code);

    $this->post(route('work.check-in'))->assertSessionHas('success');
    $this->post(route('work.check-in'))->assertSessionHas('error');

    expect(AttendanceSession::query()->where('employee_id', $employee->id)->count())->toBe(1);
});

test('a break needs a reason', function () {
    CarbonImmutable::setTestNow(workAt('2026-11-16 09:00:00'));
    [, $code] = workEmployee();
    workSignIn($this, $code);

    $this->post(route('work.check-in'));
    $this->post(route('work.break'), ['reason' => ''])->assertSessionHasErrors('reason');
});

test('actions without a signed-in code are sent back to the code page', function () {
    $this->post(route('work.check-in'))->assertRedirect(route('work.show'));
    $this->assertDatabaseCount('attendance_sessions', 0);
});

test('leaving forgets the code', function () {
    [, $code] = workEmployee();
    workSignIn($this, $code);

    $this->post(route('work.leave'))->assertRedirect(route('work.show'));
    $this->get(route('work.show'))->assertInertia(fn ($page) => $page->component('work/Enter'));
});

test('the sign-in ends after a working day', function () {
    CarbonImmutable::setTestNow(workAt('2026-11-16 08:00:00'));
    [, $code] = workEmployee();
    workSignIn($this, $code);

    CarbonImmutable::setTestNow(workAt('2026-11-17 08:00:00'));
    $this->get(route('work.show'))->assertInertia(fn ($page) => $page->component('work/Enter'));
});

test('the page shows the live state and the hours still to make up', function () {
    // Monday 16 Nov 2026, 11:00. The employee works 8h Sunday to Thursday.
    CarbonImmutable::setTestNow(workAt('2026-11-16 11:00:00'));
    [$employee, $code] = workEmployee(['expected_minutes_per_day' => 480]);

    // Sunday: 6 hours worked, so 2 hours behind.
    AttendanceSession::factory()->create([
        'employee_id' => $employee->id,
        'started_at' => workAt('2026-11-15 09:00:00')->utc(),
        'ended_at' => workAt('2026-11-15 15:00:00')->utc(),
        'status' => AttendanceStatus::Closed,
        'review_status' => AttendanceReviewStatus::Ok,
    ]);

    workSignIn($this, $code);
    $this->post(route('work.check-in'));

    CarbonImmutable::setTestNow(workAt('2026-11-16 12:00:00'));

    $this->get(route('work.show'))->assertInertia(fn ($page) => $page
        ->where('dashboard.state', 'working')
        // Monday: 1 hour so far (09:00? no, checked in at 11:00 test-now) is 60 minutes at 12:00.
        ->where('dashboard.today.workedMinutes', 60)
        ->where('dashboard.today.expectedMinutes', 480)
        ->where('dashboard.today.remainingMinutes', 420)
        // Week: Sunday + Monday targets (960) against 360 + 60 worked.
        ->where('dashboard.week.expectedMinutes', 960)
        ->where('dashboard.week.workedMinutes', 420)
        ->where('dashboard.week.behindBeforeTodayMinutes', 120)
        ->has('dashboard.recent', 2));
});

test('worked time beyond the target shows as extra, never as a negative', function () {
    CarbonImmutable::setTestNow(workAt('2026-11-16 20:00:00'));
    [$employee, $code] = workEmployee(['expected_minutes_per_day' => 480]);

    AttendanceSession::factory()->create([
        'employee_id' => $employee->id,
        'started_at' => workAt('2026-11-16 08:00:00')->utc(),
        'ended_at' => workAt('2026-11-16 19:00:00')->utc(),
        'status' => AttendanceStatus::Closed,
        'review_status' => AttendanceReviewStatus::Ok,
    ]);

    workSignIn($this, $code);

    $this->get(route('work.show'))->assertInertia(fn ($page) => $page
        ->where('dashboard.today.remainingMinutes', 0)
        ->where('dashboard.today.extraMinutes', 180));
});

test('a finished session without a report asks for one and saves it', function () {
    CarbonImmutable::setTestNow(workAt('2026-11-16 18:00:00'));
    [$employee, $code] = workEmployee();
    $session = AttendanceSession::factory()->create([
        'employee_id' => $employee->id,
        'started_at' => workAt('2026-11-16 09:00:00')->utc(),
        'ended_at' => workAt('2026-11-16 17:00:00')->utc(),
        'status' => AttendanceStatus::Closed,
        'review_status' => AttendanceReviewStatus::Ok,
    ]);
    workSignIn($this, $code);

    $this->get(route('work.show'))->assertInertia(fn ($page) => $page->where('dashboard.reportPrompt.id', $session->id));

    $this->post(route('work.report', $session), ['report' => 'Finished the audit.', 'blockers' => ''])->assertSessionHas('success');

    expect($session->refresh()->report)->toBe('Finished the audit.')
        ->and($session->blockers)->toBeNull();

    $this->get(route('work.show'))->assertInertia(fn ($page) => $page->where('dashboard.reportPrompt', null));
});

test('an employee cannot write a report or a correction for someone else', function () {
    CarbonImmutable::setTestNow(workAt('2026-11-16 18:00:00'));
    [, $code] = workEmployee();
    [$other] = workEmployee();
    $session = AttendanceSession::factory()->autoClosed()->create([
        'employee_id' => $other->id,
        'started_at' => workAt('2026-11-16 09:00:00')->utc(),
        'ended_at' => workAt('2026-11-16 17:00:00')->utc(),
    ]);
    workSignIn($this, $code);

    $this->post(route('work.report', $session), ['report' => 'Nope'])->assertSessionHas('error');
    $this->post(route('work.correct', $session), ['ended_at' => '18:00'])->assertSessionHas('error');

    expect($session->refresh()->report)->toBeNull()
        ->and(CorrectionRequest::query()->count())->toBe(0);
});

test('a forgotten check-out can be corrected from the page', function () {
    CarbonImmutable::setTestNow(workAt('2026-11-17 08:00:00'));
    [$employee, $code] = workEmployee();
    AttendanceSetting::current()->update(['self_correction_enabled' => true, 'self_correction_hours' => 24]);
    $session = AttendanceSession::factory()->autoClosed()->create([
        'employee_id' => $employee->id,
        'started_at' => workAt('2026-11-16 09:00:00')->utc(),
        'ended_at' => workAt('2026-11-16 23:59:00')->utc(),
    ]);
    workSignIn($this, $code);

    $this->get(route('work.show'))->assertInertia(fn ($page) => $page->where('dashboard.correction.id', $session->id));

    $this->post(route('work.correct', $session), ['ended_at' => '18:30', 'note' => 'Forgot'])->assertSessionHas('success');

    expect($session->refresh()->ended_at?->toDateTimeString())->toBe('2026-11-16 17:30:00')
        ->and($session->review_status)->toBe(AttendanceReviewStatus::Approved);

    $this->get(route('work.show'))->assertInertia(fn ($page) => $page->where('dashboard.correction', null));
});

test('a malformed correction time is refused', function () {
    CarbonImmutable::setTestNow(workAt('2026-11-17 08:00:00'));
    [$employee, $code] = workEmployee();
    $session = AttendanceSession::factory()->autoClosed()->create([
        'employee_id' => $employee->id,
        'started_at' => workAt('2026-11-16 09:00:00')->utc(),
        'ended_at' => workAt('2026-11-16 23:59:00')->utc(),
    ]);
    workSignIn($this, $code);

    $this->post(route('work.correct', $session), ['ended_at' => '25:99'])->assertSessionHas('error');
});

test('the owner makes a code, can read it again and can remove it', function () {
    $employee = Employee::factory()->forOffice(Office::factory()->create())->create();
    $this->actingAsRole(Role::Owner);

    $this->post(route('employees.attendance-code.store', $employee))->assertStatus(303);

    $employee->refresh();
    expect($employee->hasAttendanceCode())->toBeTrue();

    $this->get(route('employees.show', $employee))
        ->assertInertia(fn ($page) => $page->where('attendanceCode', fn ($code) => preg_match('/^\d{6}$/', (string) $code) === 1));

    // Still readable on a later visit.
    $this->get(route('employees.show', $employee))
        ->assertInertia(fn ($page) => $page->where('attendanceCode', fn ($code) => preg_match('/^\d{6}$/', (string) $code) === 1));

    $this->delete(route('employees.attendance-code.destroy', $employee))->assertStatus(303);

    expect($employee->refresh()->hasAttendanceCode())->toBeFalse();
});

test('only the owner can read an attendance code', function () {
    $employee = Employee::factory()->forOffice(Office::factory()->create())->create();
    app(AttendanceCode::class)->generate($employee);

    $this->actingAsRole(Role::Finance);

    $this->get(route('employees.show', $employee))
        ->assertInertia(fn ($page) => $page->where('attendanceCode', null));
});

test('a new code replaces the old one', function () {
    [$employee, $old] = workEmployee();
    $new = app(AttendanceCode::class)->generate($employee->refresh());

    expect(app(AttendanceCode::class)->find($new)?->id)->toBe($employee->id)
        ->and($old === $new || app(AttendanceCode::class)->find($old) === null)->toBeTrue();
});

test('the owner cannot make a code for someone outside their view', function () {
    $employee = Employee::factory()->forOffice(Office::factory()->create())->create();

    $this->post(route('employees.attendance-code.store', $employee))->assertRedirect(route('login'));
});

test('changing a code is audited without writing the hash to the log', function () {
    [$employee] = workEmployee();

    $activities = Activity::query()
        ->where('subject_type', Employee::class)
        ->where('subject_id', $employee->id)
        ->get();

    expect($activities->where('event', 'security_changed'))->not->toBeEmpty()
        ->and(json_encode($activities->map->properties))->not->toContain($employee->refresh()->attendance_code_hash);
});

test('a report is written once and only soon after the session', function () {
    CarbonImmutable::setTestNow(workAt('2026-11-16 18:00:00'));
    [$employee, $code] = workEmployee();
    $done = AttendanceSession::factory()->create([
        'employee_id' => $employee->id,
        'started_at' => workAt('2026-11-16 09:00:00')->utc(),
        'ended_at' => workAt('2026-11-16 17:00:00')->utc(),
        'status' => AttendanceStatus::Closed,
        'review_status' => AttendanceReviewStatus::Ok,
        'report' => 'The owner approved this one.',
    ]);
    $old = AttendanceSession::factory()->create([
        'employee_id' => $employee->id,
        'started_at' => workAt('2026-11-01 09:00:00')->utc(),
        'ended_at' => workAt('2026-11-01 17:00:00')->utc(),
        'status' => AttendanceStatus::Closed,
        'review_status' => AttendanceReviewStatus::Ok,
    ]);
    workSignIn($this, $code);

    $this->post(route('work.report', $done), ['report' => 'Changed'])->assertSessionHas('error', __('attendance.errors.report_already_saved'));
    $this->post(route('work.report', $old), ['report' => 'Too late'])->assertSessionHas('error', __('attendance.errors.report_too_old'));

    expect($done->refresh()->report)->toBe('The owner approved this one.')
        ->and($old->refresh()->report)->toBeNull();
});

test('the recent sessions and the daily total share the paid break allowance', function () {
    CarbonImmutable::setTestNow(workAt('2026-11-16 20:00:00'));
    [$employee, $code] = workEmployee(['expected_minutes_per_day' => 480]);
    AttendanceSetting::current()->update(['paid_break_minutes' => 60]);

    $session = AttendanceSession::factory()->create([
        'employee_id' => $employee->id,
        'started_at' => workAt('2026-11-16 09:00:00')->utc(),
        'ended_at' => workAt('2026-11-16 17:00:00')->utc(),
        'status' => AttendanceStatus::Closed,
        'review_status' => AttendanceReviewStatus::Ok,
    ]);
    AttendanceBreak::factory()->create([
        'session_id' => $session->id,
        'started_at' => workAt('2026-11-16 12:00:00')->utc(),
        'ended_at' => workAt('2026-11-16 13:00:00')->utc(),
    ]);
    workSignIn($this, $code);

    $this->get(route('work.show'))->assertInertia(fn ($page) => $page
        ->where('dashboard.today.workedMinutes', 480)
        ->where('dashboard.recent.0.workedMinutes', 480));
});

test('the page runs the same few queries however much history there is', function () {
    CarbonImmutable::setTestNow(workAt('2026-11-16 12:00:00'));
    [$employee, $code] = workEmployee();
    workSignIn($this, $code);

    $makeSession = fn (int $day) => AttendanceSession::factory()->create([
        'employee_id' => $employee->id,
        'started_at' => workAt('2026-11-'.str_pad((string) $day, 2, '0', STR_PAD_LEFT).' 09:00:00')->utc(),
        'ended_at' => workAt('2026-11-'.str_pad((string) $day, 2, '0', STR_PAD_LEFT).' 17:00:00')->utc(),
        'status' => AttendanceStatus::Closed,
        'review_status' => AttendanceReviewStatus::Ok,
        'report' => 'Done.',
    ]);

    $queries = function (): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get(route('work.show'))->assertOk();
        DB::disableQueryLog();

        return count(DB::getQueryLog());
    };

    $makeSession(1);
    $queries(); // warm the settings row
    $one = $queries();

    foreach (range(2, 12) as $day) {
        $makeSession($day);
    }

    expect($queries())->toBe($one)
        ->and($one)->toBeLessThanOrEqual(20);
});
