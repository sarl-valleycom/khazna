<?php

use App\Actions\Attendance\EndBreak;
use App\Actions\Attendance\EndSession;
use App\Actions\Attendance\StartBreak;
use App\Actions\Attendance\StartSession;
use App\Attendance\AttendanceException;
use App\Enums\AttendanceSource;
use App\Enums\AttendanceStatus;
use App\Models\AttendanceBreak;
use App\Models\AttendanceSession;
use App\Models\Employee;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

afterEach(function () {
    CarbonImmutable::setTestNow();
});

function sessionEngineAt(string $time): CarbonImmutable
{
    return CarbonImmutable::parse('2026-11-16 '.$time, 'Africa/Algiers');
}

test('check-in creates one open session and records the source', function () {
    CarbonImmutable::setTestNow(sessionEngineAt('09:00:00'));
    $employee = Employee::factory()->create();

    $result = app(StartSession::class)->handle($employee);

    expect($result->resumed)->toBeFalse()
        ->and($result->session->employee_id)->toBe($employee->id)
        ->and($result->session->status)->toBe(AttendanceStatus::Open)
        ->and($result->session->source)->toBe(AttendanceSource::Web)
        ->and($result->session->ended_at)->toBeNull();
});

test('a second check-in is refused with the original start time', function () {
    CarbonImmutable::setTestNow(sessionEngineAt('09:00:00'));
    $employee = Employee::factory()->create();

    app(StartSession::class)->handle($employee);

    CarbonImmutable::setTestNow(sessionEngineAt('09:05:00'));

    try {
        app(StartSession::class)->handle($employee);
        $this->fail('The second check-in should have been refused.');
    } catch (AttendanceException $exception) {
        expect($exception->translationKey)->toBe('attendance.errors.already_checked_in')
            ->and($exception->parameters['time'])->toBe('09:00');
    }

    expect(AttendanceSession::query()->where('employee_id', $employee->id)->count())->toBe(1);
});

test('the database rejects two open sessions even without the action', function () {
    $employee = Employee::factory()->create();

    AttendanceSession::factory()->open()->create(['employee_id' => $employee->id]);

    expect(fn () => AttendanceSession::factory()->open()->create(['employee_id' => $employee->id]))
        ->toThrow(UniqueConstraintViolationException::class);
});

test('a deactivated employee cannot start a session', function () {
    $employee = Employee::factory()->inactive()->create();

    try {
        app(StartSession::class)->handle($employee);
        $this->fail('An inactive employee should not start a session.');
    } catch (AttendanceException $exception) {
        expect($exception->translationKey)->toBe('attendance.errors.inactive');
    }

    expect(AttendanceSession::query()->count())->toBe(0);
});

test('check-in while away closes the break instead of creating a session', function () {
    CarbonImmutable::setTestNow(sessionEngineAt('09:00:00'));
    $employee = Employee::factory()->create();

    $session = app(StartSession::class)->handle($employee)->session;

    CarbonImmutable::setTestNow(sessionEngineAt('10:00:00'));
    $break = app(StartBreak::class)->handle($employee, 'Coffee');

    CarbonImmutable::setTestNow(sessionEngineAt('10:12:00'));
    $result = app(StartSession::class)->handle($employee);

    expect($result->resumed)->toBeTrue()
        ->and($result->session->id)->toBe($session->id)
        ->and($break->refresh()->ended_at?->toDateTimeString())->toBe('2026-11-16 09:12:00')
        ->and(AttendanceSession::query()->count())->toBe(1);
});

test('a break requires an open session and cannot be opened twice', function () {
    CarbonImmutable::setTestNow(sessionEngineAt('09:00:00'));
    $employee = Employee::factory()->create();

    try {
        app(StartBreak::class)->handle($employee, 'Coffee');
        $this->fail('A break without a session should fail.');
    } catch (AttendanceException $exception) {
        expect($exception->translationKey)->toBe('attendance.errors.no_open_session');
    }

    app(StartSession::class)->handle($employee);
    app(StartBreak::class)->handle($employee, 'Coffee');

    try {
        app(StartBreak::class)->handle($employee, 'Again');
        $this->fail('A second break should fail.');
    } catch (AttendanceException $exception) {
        expect($exception->translationKey)->toBe('attendance.errors.already_away');
    }

    expect(AttendanceBreak::query()->count())->toBe(1);
});

test('the database rejects two open breaks even without the action', function () {
    $session = AttendanceSession::factory()->open()->create();

    AttendanceBreak::factory()->open()->create(['session_id' => $session->id]);

    expect(fn () => AttendanceBreak::factory()->open()->create(['session_id' => $session->id]))
        ->toThrow(UniqueConstraintViolationException::class);
});

test('ending a break without being away is refused', function () {
    $employee = Employee::factory()->create();
    app(StartSession::class)->handle($employee);

    try {
        app(EndBreak::class)->handle($employee);
        $this->fail('Ending a break that does not exist should fail.');
    } catch (AttendanceException $exception) {
        expect($exception->translationKey)->toBe('attendance.errors.not_away');
    }
});

test('check-out closes an open break and the session together', function () {
    CarbonImmutable::setTestNow(sessionEngineAt('09:00:00'));
    $employee = Employee::factory()->create();

    app(StartSession::class)->handle($employee);

    CarbonImmutable::setTestNow(sessionEngineAt('12:00:00'));
    $break = app(StartBreak::class)->handle($employee, 'Lunch');

    CarbonImmutable::setTestNow(sessionEngineAt('17:00:00'));
    $session = app(EndSession::class)->handle($employee);

    expect($session->status)->toBe(AttendanceStatus::Closed)
        ->and($session->ended_at?->toDateTimeString())->toBe('2026-11-16 16:00:00')
        ->and($break->refresh()->ended_at?->toDateTimeString())->toBe('2026-11-16 16:00:00')
        ->and($session->breaks)->toHaveCount(1);
});

test('check-out without an open session is refused', function () {
    $employee = Employee::factory()->create();

    try {
        app(EndSession::class)->handle($employee);
        $this->fail('A check-out without a session should fail.');
    } catch (AttendanceException $exception) {
        expect($exception->translationKey)->toBe('attendance.errors.no_open_session');
    }
});
