<?php

use App\Attendance\AttendanceCode;
use App\Enums\AttendanceReviewStatus;
use App\Enums\AttendanceStatus;
use App\Enums\CorrectionStatus;
use App\Models\AttendanceSession;
use App\Models\AttendanceSetting;
use App\Models\CorrectionRequest;
use App\Models\Employee;
use App\Models\Office;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

afterEach(function () {
    CarbonImmutable::setTestNow();
});

function correctionAt(string $dateTime): CarbonImmutable
{
    return CarbonImmutable::parse($dateTime, 'Africa/Algiers');
}

/**
 * An employee already signed in on the web page.
 */
function correctionEmployee(object $test): Employee
{
    $employee = Employee::factory()->forOffice(Office::factory()->create())->create();
    $code = app(AttendanceCode::class)->generate($employee);

    $test->post(route('work.enter'), ['code' => $code])->assertRedirect(route('work.show'));

    return $employee;
}

function autoClosedSession(Employee $employee, string $endedAt = '2026-11-16 17:00:00'): AttendanceSession
{
    return AttendanceSession::factory()->autoClosed()->create([
        'employee_id' => $employee->id,
        'started_at' => correctionAt('2026-11-16 09:00:00')->utc(),
        'ended_at' => correctionAt($endedAt)->utc(),
    ]);
}

function selfCorrection(bool $enabled, int $hours = 24): void
{
    AttendanceSetting::current()->update(['self_correction_enabled' => $enabled, 'self_correction_hours' => $hours]);
}

test('a correction is saved as pending when self-correction is off', function () {
    CarbonImmutable::setTestNow(correctionAt('2026-11-17 08:00:00'));
    $employee = correctionEmployee($this);
    $session = autoClosedSession($employee);
    selfCorrection(false);

    $this->post(route('work.correct', $session), ['ended_at' => '18:30', 'note' => 'Forgot to check out'])
        ->assertSessionHas('success', 'Thanks. Your correction is waiting for the owner to approve.');

    $request = CorrectionRequest::query()->sole();

    expect($request->status)->toBe(CorrectionStatus::Pending)
        ->and($request->proposed_ended_at?->toDateTimeString())->toBe('2026-11-16 17:30:00')
        ->and($request->note)->toBe('Forgot to check out')
        ->and($session->refresh()->ended_at?->toDateTimeString())->toBe('2026-11-16 16:00:00')
        ->and($session->review_status)->toBe(AttendanceReviewStatus::NeedsReview);
});

test('self-correction applies immediately when enabled and within 24 hours', function () {
    CarbonImmutable::setTestNow(correctionAt('2026-11-17 08:00:00'));
    $employee = correctionEmployee($this);
    $session = autoClosedSession($employee);
    selfCorrection(true);

    $this->post(route('work.correct', $session), ['ended_at' => '18:30'])
        ->assertSessionHas('success', 'End time corrected to 18:30. Thank you.');

    expect($session->refresh()->ended_at?->toDateTimeString())->toBe('2026-11-16 17:30:00')
        ->and($session->review_status)->toBe(AttendanceReviewStatus::Approved)
        ->and(CorrectionRequest::query()->sole()->status)->toBe(CorrectionStatus::Approved);
});

test('self-correction outside the window still waits for the owner', function () {
    CarbonImmutable::setTestNow(correctionAt('2026-11-17 18:00:00'));
    $employee = correctionEmployee($this);
    $session = autoClosedSession($employee);
    selfCorrection(true);

    $this->post(route('work.correct', $session), ['ended_at' => '18:30'])->assertSessionHas('success');

    expect(CorrectionRequest::query()->sole()->status)->toBe(CorrectionStatus::Pending)
        ->and($session->refresh()->review_status)->toBe(AttendanceReviewStatus::NeedsReview);
});

test('a malformed end time is refused before anything is written', function () {
    CarbonImmutable::setTestNow(correctionAt('2026-11-17 08:00:00'));
    $employee = correctionEmployee($this);
    $session = autoClosedSession($employee);

    $this->post(route('work.correct', $session), ['ended_at' => '25:99'])
        ->assertSessionHas('error', 'Use HH:MM in Algiers time.');

    expect(CorrectionRequest::query()->count())->toBe(0);
});

test('an employee cannot correct another employee session', function () {
    CarbonImmutable::setTestNow(correctionAt('2026-11-17 08:00:00'));
    correctionEmployee($this);
    $other = Employee::factory()->forOffice(Office::factory()->create())->create();
    $session = autoClosedSession($other);

    $this->post(route('work.correct', $session), ['ended_at' => '18:30'])
        ->assertSessionHas('error', 'That session was not found.');

    expect(CorrectionRequest::query()->count())->toBe(0);
});

test('a manually closed session cannot be self-corrected', function () {
    CarbonImmutable::setTestNow(correctionAt('2026-11-17 08:00:00'));
    $employee = correctionEmployee($this);
    $session = autoClosedSession($employee);
    $session->update(['status' => AttendanceStatus::Closed]);
    selfCorrection(true);

    $this->post(route('work.correct', $session), ['ended_at' => '18:30'])->assertSessionHas('success');

    expect(CorrectionRequest::query()->sole()->status)->toBe(CorrectionStatus::Pending)
        ->and($session->refresh()->ended_at?->toDateTimeString())->toBe('2026-11-16 16:00:00');
});

test('a self-approved correction cannot be repeated to extend the session again', function () {
    CarbonImmutable::setTestNow(correctionAt('2026-11-17 08:00:00'));
    $employee = correctionEmployee($this);
    $session = autoClosedSession($employee);
    selfCorrection(true);

    $this->post(route('work.correct', $session), ['ended_at' => '18:30'])->assertSessionHas('success');

    expect($session->refresh()->ended_at?->toDateTimeString())->toBe('2026-11-16 17:30:00');

    CarbonImmutable::setTestNow(correctionAt('2026-11-17 17:00:00'));

    $this->post(route('work.correct', $session), ['ended_at' => '19:30'])->assertSessionHas('success');

    expect($session->refresh()->ended_at?->toDateTimeString())->toBe('2026-11-16 17:30:00')
        ->and(CorrectionRequest::query()->where('status', CorrectionStatus::Pending)->count())->toBe(1);
});

test('a self-correction that would make the session longer than 12 hours waits for the owner', function () {
    CarbonImmutable::setTestNow(correctionAt('2026-11-17 08:00:00'));
    $employee = correctionEmployee($this);
    $session = autoClosedSession($employee);
    selfCorrection(true);

    // Session started 09:00; 22:30 is 13h30 later.
    $this->post(route('work.correct', $session), ['ended_at' => '22:30'])->assertSessionHas('success');

    expect(CorrectionRequest::query()->sole()->status)->toBe(CorrectionStatus::Pending)
        ->and($session->refresh()->ended_at?->toDateTimeString())->toBe('2026-11-16 16:00:00')
        ->and($session->review_status)->toBe(AttendanceReviewStatus::NeedsReview);
});

test('self-correction is on by default for a fresh installation', function () {
    CarbonImmutable::setTestNow(correctionAt('2026-11-17 08:00:00'));
    $employee = correctionEmployee($this);
    $session = autoClosedSession($employee);

    expect(AttendanceSetting::current()->self_correction_enabled)->toBeTrue();

    $this->post(route('work.correct', $session), ['ended_at' => '18:30'])
        ->assertSessionHas('success', 'End time corrected to 18:30. Thank you.');
});
