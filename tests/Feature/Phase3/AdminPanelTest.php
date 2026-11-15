<?php

use App\Enums\AttendanceReviewStatus;
use App\Enums\AttendanceSource;
use App\Enums\AttendanceStatus;
use App\Enums\CorrectionStatus;
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
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

afterEach(function () {
    CarbonImmutable::setTestNow();
});

function adminAt(string $dateTime): CarbonImmutable
{
    return CarbonImmutable::parse($dateTime, 'Africa/Algiers');
}

/**
 * @param  array<string, mixed>  $attributes
 */
function adminSessionFor(Employee $employee, string $startedAt, ?string $endedAt, array $attributes = []): AttendanceSession
{
    return AttendanceSession::factory()->create([
        'employee_id' => $employee->id,
        'started_at' => adminAt($startedAt)->utc(),
        'ended_at' => $endedAt === null ? null : adminAt($endedAt)->utc(),
        ...$attributes,
    ]);
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function adminSessionPayload(Employee $employee, array $overrides = []): array
{
    return [
        'employee_id' => $employee->id,
        'started_at' => '2026-11-16T09:00',
        'ended_at' => '2026-11-16T17:00',
        'report' => 'Fixed the import.',
        'blockers' => null,
        'reason' => 'Paper timesheet',
        ...$overrides,
    ];
}

function adminEmployee(string $name): Employee
{
    return Employee::factory()->create(['name' => $name]);
}

test('guests are redirected and non-owners cannot open the attendance pages', function () {
    $this->get(route('attendance.sessions.index'))->assertRedirect(route('login'));
    $this->get(route('attendance.presence.index'))->assertRedirect(route('login'));
    $this->get(route('attendance.corrections.index'))->assertRedirect(route('login'));
    $this->get(route('attendance.settings.edit'))->assertRedirect(route('login'));

    foreach ([Role::Finance, Role::AccountManager] as $role) {
        $this->actingAsRole($role);

        $this->get(route('attendance.sessions.index'))->assertForbidden();
        $this->get(route('attendance.presence.index'))->assertForbidden();
        $this->get(route('attendance.corrections.index'))->assertForbidden();
        $this->get(route('attendance.settings.edit'))->assertForbidden();
    }
});

test('the session list filters by employee, office, date and status', function () {
    $officeA = Office::factory()->create(['name' => 'Algiers']);
    $officeB = Office::factory()->create(['name' => 'Oran']);
    $alice = Employee::factory()->forOffice($officeA)->create(['name' => 'Alice']);
    $bob = Employee::factory()->forOffice($officeB)->create(['name' => 'Bob']);

    adminSessionFor($alice, '2026-11-16 09:00:00', '2026-11-16 17:00:00');
    adminSessionFor($bob, '2026-11-17 09:00:00', '2026-11-17 17:00:00', [
        'status' => AttendanceStatus::AutoClosed,
        'review_status' => AttendanceReviewStatus::NeedsReview,
    ]);

    $this->actingAsRole(Role::Owner);

    $this->get(route('attendance.sessions.index', [
        'q' => 'Ali',
        'employee' => $alice->id,
        'office' => $officeA->id,
        'status' => AttendanceStatus::Closed->value,
        'from' => '2026-11-16',
        'to' => '2026-11-16',
    ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('attendance/Sessions')
            ->where('total', 1)
            ->where('sessions.0.employeeName', 'Alice')
            ->where('sessions.0.officeName', 'Algiers')
            ->where('filters.employee', $alice->id)
            ->where('filters.office', $officeA->id)
            ->where('filters.status', AttendanceStatus::Closed->value));

    $this->get(route('attendance.sessions.index', ['office' => $officeB->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('total', 1)
            ->where('sessions.0.employeeName', 'Bob'));

    $this->get(route('attendance.sessions.index', ['from' => '2026-11-18']))
        ->assertInertia(fn (Assert $page) => $page->where('total', 0));
});

test('the owner can add a session with a reason and it is audited', function () {
    $office = Office::factory()->create();
    $employee = Employee::factory()->forOffice($office)->create();
    $this->actingAsRole(Role::Owner);

    $this->post(route('attendance.sessions.store'), adminSessionPayload($employee))
        ->assertStatus(303)
        ->assertSessionHasNoErrors();

    $session = AttendanceSession::query()->sole();

    expect($session->employee_id)->toBe($employee->id)
        ->and($session->source)->toBe(AttendanceSource::Admin)
        ->and($session->status)->toBe(AttendanceStatus::Closed)
        ->and($session->review_status)->toBe(AttendanceReviewStatus::Approved)
        ->and($session->started_at?->toDateTimeString())->toBe('2026-11-16 08:00:00')
        ->and($session->ended_at?->toDateTimeString())->toBe('2026-11-16 16:00:00')
        ->and($session->report)->toBe('Fixed the import.');

    $activity = Activity::query()
        ->where('subject_type', AttendanceSession::class)
        ->where('event', 'created')
        ->latest('id')
        ->firstOrFail();

    expect($activity->getProperty('reason'))->toBe('Paper timesheet');
});

test('a manual session without a reason is refused', function () {
    $employee = Employee::factory()->create();
    $this->actingAsRole(Role::Owner);

    $this->post(route('attendance.sessions.store'), adminSessionPayload($employee, ['reason' => '']))
        ->assertSessionHasErrors('reason');

    expect(AttendanceSession::query()->count())->toBe(0);
});

test('an employee cannot have two open sessions even through the admin form', function () {
    $employee = Employee::factory()->create();
    $this->actingAsRole(Role::Owner);

    $this->post(route('attendance.sessions.store'), adminSessionPayload($employee, [
        'ended_at' => null,
        'started_at' => '2026-11-16T09:00',
    ]))->assertSessionHasNoErrors();

    $this->post(route('attendance.sessions.store'), adminSessionPayload($employee, [
        'ended_at' => null,
        'started_at' => '2026-11-16T10:00',
    ]))->assertSessionHasErrors('ended_at');

    expect(AttendanceSession::query()->open()->count())->toBe(1);
});

test('editing a session records the old and new values with a reason', function () {
    $employee = Employee::factory()->create();
    $session = adminSessionFor($employee, '2026-11-16 09:00:00', '2026-11-16 17:00:00');
    $this->actingAsRole(Role::Owner);

    $this->patch(route('attendance.sessions.update', $session), adminSessionPayload($employee, [
        'started_at' => '2026-11-16T09:30',
        'ended_at' => '2026-11-16T18:00',
        'reason' => 'Employee stayed late.',
    ]))->assertStatus(303)->assertSessionHasNoErrors();

    $session->refresh();

    expect($session->started_at?->toDateTimeString())->toBe('2026-11-16 08:30:00')
        ->and($session->ended_at?->toDateTimeString())->toBe('2026-11-16 17:00:00')
        ->and($session->review_status)->toBe(AttendanceReviewStatus::Approved);

    $activity = Activity::query()
        ->where('subject_type', AttendanceSession::class)
        ->where('subject_id', $session->id)
        ->where('event', 'updated')
        ->latest('id')
        ->firstOrFail();

    $changes = $activity->attribute_changes?->toArray() ?? [];

    expect($activity->getProperty('reason'))->toBe('Employee stayed late.')
        ->and($changes['old']['ended_at'] ?? null)->toContain('16:00:00')
        ->and($changes['attributes']['ended_at'] ?? null)->toContain('17:00:00');
});

test('editing an auto-closed session keeps its status and clears the review flag', function () {
    $employee = Employee::factory()->create();
    $session = adminSessionFor($employee, '2026-11-16 09:00:00', '2026-11-16 17:00:00', [
        'status' => AttendanceStatus::AutoClosed,
        'review_status' => AttendanceReviewStatus::NeedsReview,
    ]);
    $this->actingAsRole(Role::Owner);

    $this->patch(route('attendance.sessions.update', $session), adminSessionPayload($employee, [
        'ended_at' => '2026-11-16T18:30',
    ]))->assertSessionHasNoErrors();

    expect($session->refresh()->status)->toBe(AttendanceStatus::AutoClosed)
        ->and($session->review_status)->toBe(AttendanceReviewStatus::Approved);
});

test('a session cannot be moved to another employee', function () {
    $employee = Employee::factory()->create();
    $other = Employee::factory()->create();
    $session = adminSessionFor($employee, '2026-11-16 09:00:00', '2026-11-16 17:00:00');
    $this->actingAsRole(Role::Owner);

    $this->patch(route('attendance.sessions.update', $session), adminSessionPayload($other))
        ->assertSessionHasErrors('employee_id');

    expect($session->refresh()->employee_id)->toBe($employee->id);
});

test('the owner approves a session with a reason', function () {
    $employee = Employee::factory()->create();
    $session = adminSessionFor($employee, '2026-11-16 09:00:00', '2026-11-16 17:00:00', [
        'status' => AttendanceStatus::AutoClosed,
        'review_status' => AttendanceReviewStatus::NeedsReview,
    ]);
    $this->actingAsRole(Role::Owner);

    $this->post(route('attendance.sessions.approve', $session), ['reason' => 'End time checked.'])
        ->assertStatus(303);

    expect($session->refresh()->review_status)->toBe(AttendanceReviewStatus::Approved);

    $activity = Activity::query()
        ->where('subject_type', AttendanceSession::class)
        ->where('subject_id', $session->id)
        ->where('event', 'updated')
        ->latest('id')
        ->firstOrFail();

    expect($activity->getProperty('reason'))->toBe('End time checked.');
});

test('deleting a session deletes its breaks and keeps the reason in the audit log', function () {
    $employee = Employee::factory()->create();
    $session = adminSessionFor($employee, '2026-11-16 09:00:00', '2026-11-16 17:00:00');
    $break = AttendanceBreak::factory()->create([
        'session_id' => $session->id,
        'started_at' => adminAt('2026-11-16 12:00:00')->utc(),
        'ended_at' => adminAt('2026-11-16 12:30:00')->utc(),
    ]);
    $this->actingAsRole(Role::Owner);

    $this->delete(route('attendance.sessions.destroy', $session), ['reason' => 'Duplicate entry.'])
        ->assertStatus(303);

    expect(AttendanceSession::withTrashed()->whereKey($session->id)->sole()->deleted_at)->not->toBeNull()
        ->and(AttendanceBreak::withTrashed()->whereKey($break->id)->sole()->deleted_at)->not->toBeNull();

    $activity = Activity::query()
        ->where('subject_type', AttendanceSession::class)
        ->where('subject_id', $session->id)
        ->where('event', 'deleted')
        ->latest('id')
        ->firstOrFail();

    expect($activity->getProperty('reason'))->toBe('Duplicate entry.');
});

test('presence shows working, away and offline employees', function () {
    CarbonImmutable::setTestNow(adminAt('2026-11-16 12:00:00'));

    $working = adminEmployee('Working');
    $away = adminEmployee('Away');
    adminEmployee('Offline');
    Employee::factory()->create(['name' => 'Inactive', 'status' => EmployeeStatus::Inactive]);

    $workingSession = adminSessionFor($working, '2026-11-16 09:00:00', null, [
        'status' => AttendanceStatus::Open,
        'review_status' => AttendanceReviewStatus::Ok,
    ]);
    $awaySession = adminSessionFor($away, '2026-11-16 09:00:00', null, [
        'status' => AttendanceStatus::Open,
        'review_status' => AttendanceReviewStatus::Ok,
    ]);
    AttendanceBreak::factory()->open()->create([
        'session_id' => $awaySession->id,
        'started_at' => adminAt('2026-11-16 11:00:00')->utc(),
    ]);

    $this->actingAsRole(Role::Owner);

    $page = $this->get(route('attendance.presence.index'))->assertOk()->viewData('page');
    $rows = collect($page['props']['presence']);

    expect($rows)->toHaveCount(3)
        ->and($rows->firstWhere('name', 'Working')['state'])->toBe('working')
        ->and($rows->firstWhere('name', 'Working')['todayMinutes'])->toBe(180)
        ->and($rows->firstWhere('name', 'Away')['state'])->toBe('away')
        ->and($rows->firstWhere('name', 'Away')['todayMinutes'])->toBe(120)
        ->and($rows->firstWhere('name', 'Offline')['state'])->toBe('offline')
        ->and($rows->firstWhere('name', 'Offline')['todayMinutes'])->toBe(0)
        ->and($workingSession->isOpen())->toBeTrue();
});

test('the correction inbox approves and rejects requests', function () {
    CarbonImmutable::setTestNow(adminAt('2026-11-17 09:00:00'));

    $employee = adminEmployee('Correction');
    $first = adminSessionFor($employee, '2026-11-16 09:00:00', '2026-11-16 17:00:00', [
        'status' => AttendanceStatus::AutoClosed,
        'review_status' => AttendanceReviewStatus::NeedsReview,
    ]);
    $second = adminSessionFor($employee, '2026-11-15 09:00:00', '2026-11-15 17:00:00', [
        'status' => AttendanceStatus::AutoClosed,
        'review_status' => AttendanceReviewStatus::NeedsReview,
    ]);

    $approve = CorrectionRequest::factory()->create([
        'session_id' => $first->id,
        'employee_id' => $employee->id,
        'proposed_ended_at' => adminAt('2026-11-16 18:30:00')->utc(),
    ]);
    $reject = CorrectionRequest::factory()->create([
        'session_id' => $second->id,
        'employee_id' => $employee->id,
        'proposed_ended_at' => adminAt('2026-11-15 18:00:00')->utc(),
    ]);

    $owner = $this->actingAsRole(Role::Owner);

    $this->get(route('attendance.corrections.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('attendance/Corrections')->has('requests', 2));

    $this->patch(route('attendance.corrections.approve', $approve), ['reason' => 'Time confirmed by client.'])
        ->assertStatus(303);

    $first->refresh();
    $approve->refresh();

    expect($first->ended_at?->toDateTimeString())->toBe('2026-11-16 17:30:00')
        ->and($first->review_status)->toBe(AttendanceReviewStatus::Approved)
        ->and($approve->status)->toBe(CorrectionStatus::Approved)
        ->and($approve->decided_by)->toBe($owner->id)
        ->and($approve->decided_at)->not->toBeNull();

    $this->patch(route('attendance.corrections.reject', $reject), ['reason' => 'No evidence.'])
        ->assertStatus(303);

    $second->refresh();
    $reject->refresh();

    expect($second->ended_at?->toDateTimeString())->toBe('2026-11-15 16:00:00')
        ->and($second->review_status)->toBe(AttendanceReviewStatus::NeedsReview)
        ->and($reject->status)->toBe(CorrectionStatus::Rejected);

    $reason = Activity::query()
        ->where('subject_type', CorrectionRequest::class)
        ->where('subject_id', $approve->id)
        ->where('event', 'updated')
        ->latest('id')
        ->firstOrFail()
        ->getProperty('reason');

    expect($reason)->toBe('Time confirmed by client.');
});

test('a decided correction cannot be decided twice', function () {
    $employee = adminEmployee('Twice');
    $session = adminSessionFor($employee, '2026-11-16 09:00:00', '2026-11-16 17:00:00', [
        'status' => AttendanceStatus::AutoClosed,
        'review_status' => AttendanceReviewStatus::NeedsReview,
    ]);
    $request = CorrectionRequest::factory()->create([
        'session_id' => $session->id,
        'employee_id' => $employee->id,
        'proposed_ended_at' => adminAt('2026-11-16 18:00:00')->utc(),
    ]);
    $this->actingAsRole(Role::Owner);

    $this->patch(route('attendance.corrections.reject', $request), ['reason' => 'First.'])
        ->assertStatus(303);
    $this->patch(route('attendance.corrections.reject', $request), ['reason' => 'Second.'])
        ->assertSessionHasErrors('status');

    expect($request->refresh()->status)->toBe(CorrectionStatus::Rejected);
});

test('the owner edits attendance settings, channels and the change is audited', function () {
    $office = Office::factory()->create(['name' => 'Algiers']);
    $owner = $this->actingAsRole(Role::Owner);

    $this->get(route('attendance.settings.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('attendance/Settings')
            ->where('settings.autoCloseTime', '23:59')
            ->where('settings.offices.0.id', $office->id));

    $this->patch(route('attendance.settings.update'), [
        'auto_close_time' => '22:30',
        'reminders_enabled' => false,
        'reminder_after_minutes' => 45,
        'away_limit_minutes' => 60,
        'paid_break_minutes' => 15,
        'self_correction_enabled' => true,
        'self_correction_hours' => 12,
        'over_10h_reminder_enabled' => false,
        'reports_channel_id' => '223456789012345678',
        'offices' => [[
            'id' => $office->id,
            'attendance_channel_id' => '323456789012345678',
        ]],
    ])->assertRedirect(route('attendance.settings.edit'))->assertStatus(303);

    $settings = AttendanceSetting::current();

    expect($settings->auto_close_time)->toBe('22:30:00')
        ->and($settings->reminders_enabled)->toBeFalse()
        ->and($settings->reminder_after_minutes)->toBe(45)
        ->and($settings->away_limit_minutes)->toBe(60)
        ->and($settings->paid_break_minutes)->toBe(15)
        ->and($settings->self_correction_enabled)->toBeTrue()
        ->and($settings->self_correction_hours)->toBe(12)
        ->and($settings->over_10h_reminder_enabled)->toBeFalse()
        ->and($settings->reports_channel_id)->toBe('223456789012345678')
        ->and($office->refresh()->attendance_channel_id)->toBe('323456789012345678');

    $activity = Activity::query()
        ->where('subject_type', AttendanceSetting::class)
        ->where('event', 'updated')
        ->latest('id')
        ->firstOrFail();

    expect($activity->getProperty('reason'))->toBe('Attendance settings updated by the owner')
        ->and($activity->causer_id)->toBe($owner->id);
});

test('attendance settings reject malformed Discord channel IDs', function () {
    Office::factory()->create();
    $this->actingAsRole(Role::Owner);

    $this->patch(route('attendance.settings.update'), [
        'auto_close_time' => '23:59',
        'reminders_enabled' => true,
        'reminder_after_minutes' => 30,
        'away_limit_minutes' => 90,
        'paid_break_minutes' => 0,
        'self_correction_enabled' => false,
        'self_correction_hours' => 24,
        'over_10h_reminder_enabled' => true,
        'reports_channel_id' => '123',
    ])->assertSessionHasErrors(['reports_channel_id']);
});

test('deleting a session rejects its pending correction requests', function () {
    CarbonImmutable::setTestNow(adminAt('2026-11-17 09:00:00'));

    $employee = adminEmployee('Deleted');
    $session = adminSessionFor($employee, '2026-11-16 09:00:00', '2026-11-16 17:00:00', [
        'status' => AttendanceStatus::AutoClosed,
        'review_status' => AttendanceReviewStatus::NeedsReview,
    ]);
    $request = CorrectionRequest::factory()->create([
        'session_id' => $session->id,
        'employee_id' => $employee->id,
        'proposed_ended_at' => adminAt('2026-11-16 18:30:00')->utc(),
    ]);
    $this->actingAsRole(Role::Owner);

    $this->delete(route('attendance.sessions.destroy', $session), ['reason' => 'Duplicate entry.'])
        ->assertStatus(303);

    $request->refresh();

    expect($request->status)->toBe(CorrectionStatus::Rejected)
        ->and($request->decided_at)->not->toBeNull()
        ->and(CorrectionRequest::query()->pending()->count())->toBe(0);
});

test('editing the end of a session rejects its stale pending correction', function () {
    CarbonImmutable::setTestNow(adminAt('2026-11-17 09:00:00'));

    $employee = adminEmployee('Edited');
    $session = adminSessionFor($employee, '2026-11-16 09:00:00', '2026-11-16 17:00:00', [
        'status' => AttendanceStatus::AutoClosed,
        'review_status' => AttendanceReviewStatus::NeedsReview,
    ]);
    $request = CorrectionRequest::factory()->create([
        'session_id' => $session->id,
        'employee_id' => $employee->id,
        'proposed_ended_at' => adminAt('2026-11-16 18:30:00')->utc(),
    ]);
    $this->actingAsRole(Role::Owner);

    $this->patch(route('attendance.sessions.update', $session), adminSessionPayload($employee, [
        'ended_at' => '2026-11-16T17:45',
        'reason' => 'Checked with the employee.',
    ]))->assertSessionHasNoErrors();

    expect($request->refresh()->status)->toBe(CorrectionStatus::Rejected)
        ->and($session->refresh()->ended_at?->toDateTimeString())->toBe('2026-11-16 16:45:00');
});

test('editing only the report leaves a pending correction alone', function () {
    CarbonImmutable::setTestNow(adminAt('2026-11-17 09:00:00'));

    $employee = adminEmployee('Report');
    $session = adminSessionFor($employee, '2026-11-16 09:00:00', '2026-11-16 17:00:00', [
        'status' => AttendanceStatus::AutoClosed,
        'review_status' => AttendanceReviewStatus::NeedsReview,
    ]);
    $request = CorrectionRequest::factory()->create([
        'session_id' => $session->id,
        'employee_id' => $employee->id,
        'proposed_ended_at' => adminAt('2026-11-16 18:30:00')->utc(),
    ]);
    $this->actingAsRole(Role::Owner);

    $this->patch(route('attendance.sessions.update', $session), adminSessionPayload($employee, [
        'ended_at' => '2026-11-16T17:00',
        'report' => 'A better report.',
        'reason' => 'Report typo.',
    ]))->assertSessionHasNoErrors();

    expect($request->refresh()->status)->toBe(CorrectionStatus::Pending);
});

test('the presence page runs the same number of queries however many employees there are', function () {
    CarbonImmutable::setTestNow(adminAt('2026-11-16 12:00:00'));
    $this->actingAsRole(Role::Owner);

    $queriesFor = function (): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get(route('attendance.presence.index'))->assertOk();
        DB::disableQueryLog();

        return count(DB::getQueryLog());
    };

    foreach (range(1, 2) as $number) {
        $employee = adminEmployee('First '.$number);
        adminSessionFor($employee, '2026-11-16 09:00:00', '2026-11-16 11:00:00');
    }

    $queriesFor(); // warm caches such as the settings row
    $few = $queriesFor();

    foreach (range(1, 8) as $number) {
        $employee = adminEmployee('More '.$number);
        adminSessionFor($employee, '2026-11-16 09:00:00', '2026-11-16 11:00:00');
    }

    expect($queriesFor())->toBe($few);
});
