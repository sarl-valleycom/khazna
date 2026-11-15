<?php

use App\Actions\Attendance\AutoCloseSession;
use App\Actions\Attendance\StartBreak;
use App\Actions\Attendance\StartSession;
use App\Enums\AttendanceReviewStatus;
use App\Enums\AttendanceStatus;
use App\Models\AttendanceSession;
use App\Models\Employee;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

afterEach(function () {
    CarbonImmutable::setTestNow();
});

function autoCloseAt(string $dateTime): CarbonImmutable
{
    return CarbonImmutable::parse($dateTime, 'Africa/Algiers');
}

test('an auto-closed session ends at the scheduled end and needs review', function () {
    CarbonImmutable::setTestNow(autoCloseAt('2026-11-16 09:00:00'));
    $employee = Employee::factory()->scheduled('09:00', '17:00')->create();
    $session = app(StartSession::class)->handle($employee)->session;

    CarbonImmutable::setTestNow(autoCloseAt('2026-11-16 23:59:00'));
    $closed = app(AutoCloseSession::class)->handle($session);

    expect($closed->status)->toBe(AttendanceStatus::AutoClosed)
        ->and($closed->review_status)->toBe(AttendanceReviewStatus::NeedsReview)
        ->and($closed->ended_at?->toDateTimeString())->toBe('2026-11-16 16:00:00');
});

test('without a schedule the end is start plus the expected minutes', function () {
    CarbonImmutable::setTestNow(autoCloseAt('2026-11-16 09:00:00'));
    $employee = Employee::factory()->create(['expected_minutes_per_day' => 420]);
    $session = app(StartSession::class)->handle($employee)->session;

    CarbonImmutable::setTestNow(autoCloseAt('2026-11-16 23:59:00'));
    $closed = app(AutoCloseSession::class)->handle($session);

    expect($closed->ended_at?->toDateTimeString())->toBe('2026-11-16 15:00:00');
});

test('a session started after its scheduled end closes at the current time', function () {
    CarbonImmutable::setTestNow(autoCloseAt('2026-11-16 18:00:00'));
    $employee = Employee::factory()->scheduled('09:00', '17:00')->create();
    $session = app(StartSession::class)->handle($employee)->session;

    CarbonImmutable::setTestNow(autoCloseAt('2026-11-16 23:59:00'));
    $closed = app(AutoCloseSession::class)->handle($session);

    expect($closed->ended_at?->toDateTimeString())->toBe('2026-11-16 22:59:00');
});

test('an overnight schedule ends on the following day', function () {
    CarbonImmutable::setTestNow(autoCloseAt('2026-11-16 22:00:00'));
    $employee = Employee::factory()->scheduled('22:00', '06:00')->create();
    $session = app(StartSession::class)->handle($employee)->session;

    CarbonImmutable::setTestNow(autoCloseAt('2026-11-16 23:59:00'));
    $closed = app(AutoCloseSession::class)->handle($session);

    // The scheduled end is in the future, so the session closes now.
    expect($closed->ended_at?->toDateTimeString())->toBe('2026-11-16 22:59:00');
});

test('an open break is closed at the session end', function () {
    CarbonImmutable::setTestNow(autoCloseAt('2026-11-16 09:00:00'));
    $employee = Employee::factory()->scheduled('09:00', '17:00')->create();
    $session = app(StartSession::class)->handle($employee)->session;

    CarbonImmutable::setTestNow(autoCloseAt('2026-11-16 12:00:00'));
    $break = app(StartBreak::class)->handle($employee, 'Lunch');

    CarbonImmutable::setTestNow(autoCloseAt('2026-11-16 23:59:00'));
    app(AutoCloseSession::class)->handle($session);

    expect($break->refresh()->ended_at?->toDateTimeString())->toBe('2026-11-16 16:00:00');
});

test('the command closes every session still open', function () {
    CarbonImmutable::setTestNow(autoCloseAt('2026-11-16 09:00:00'));

    $first = Employee::factory()->scheduled('09:00', '17:00')->create();
    $second = Employee::factory()->scheduled('09:00', '17:00')->create();

    app(StartSession::class)->handle($first);
    app(StartSession::class)->handle($second);

    CarbonImmutable::setTestNow(autoCloseAt('2026-11-16 23:59:00'));

    $this->artisan('attendance:auto-close')
        ->expectsOutput('Auto-closed 2 session(s).')
        ->assertSuccessful();

    expect(AttendanceSession::query()->open()->count())->toBe(0)
        ->and(AttendanceSession::query()->where('status', AttendanceStatus::AutoClosed)->count())->toBe(2);
});

test('the command does nothing before the auto-close time', function () {
    CarbonImmutable::setTestNow(autoCloseAt('2026-11-16 09:00:00'));
    app(StartSession::class)->handle(Employee::factory()->scheduled('09:00', '17:00')->create());

    CarbonImmutable::setTestNow(autoCloseAt('2026-11-16 20:00:00'));

    $this->artisan('attendance:auto-close')->assertSuccessful();

    expect(AttendanceSession::query()->open()->count())->toBe(1);
});

test('a missed scheduler tick is caught up on the next run', function () {
    CarbonImmutable::setTestNow(autoCloseAt('2026-11-16 09:00:00'));
    app(StartSession::class)->handle(Employee::factory()->scheduled('09:00', '17:00')->create());

    // 23:59 passed without a run; the next tick is at 00:20 the next day.
    CarbonImmutable::setTestNow(autoCloseAt('2026-11-17 00:20:00'));

    $this->artisan('attendance:auto-close')->assertSuccessful();

    expect(AttendanceSession::query()->open()->count())->toBe(0)
        ->and(AttendanceSession::query()->where('status', AttendanceStatus::AutoClosed)->count())->toBe(1);
});

test('a session started after the last auto-close time is left open', function () {
    CarbonImmutable::setTestNow(autoCloseAt('2026-11-17 00:10:00'));
    app(StartSession::class)->handle(Employee::factory()->create());

    CarbonImmutable::setTestNow(autoCloseAt('2026-11-17 00:20:00'));

    $this->artisan('attendance:auto-close')->assertSuccessful();

    expect(AttendanceSession::query()->open()->count())->toBe(1);
});

test('the all option closes every open session at once', function () {
    CarbonImmutable::setTestNow(autoCloseAt('2026-11-16 09:00:00'));
    app(StartSession::class)->handle(Employee::factory()->scheduled('09:00', '17:00')->create());

    CarbonImmutable::setTestNow(autoCloseAt('2026-11-16 12:00:00'));

    $this->artisan('attendance:auto-close', ['--all' => true])
        ->expectsOutput('Auto-closed 1 session(s).')
        ->assertSuccessful();

    expect(AttendanceSession::query()->open()->count())->toBe(0);
});
