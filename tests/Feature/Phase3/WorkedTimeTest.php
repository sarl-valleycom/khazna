<?php

use App\Attendance\WorkedTime;
use App\Models\AttendanceBreak;
use App\Models\AttendanceSession;
use App\Models\Employee;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

afterEach(function () {
    CarbonImmutable::setTestNow();
});

function algiers(string $dateTime): CarbonImmutable
{
    return CarbonImmutable::parse($dateTime, 'Africa/Algiers')->utc();
}

test('a session crossing midnight counts on the day it started', function () {
    $session = AttendanceSession::factory()->create([
        'started_at' => algiers('2026-11-15 22:00:00'),
        'ended_at' => algiers('2026-11-16 02:00:00'),
    ]);

    expect(WorkedTime::sessionMinutes($session))->toBe(240);
});

test('several breaks are subtracted from the session', function () {
    $session = AttendanceSession::factory()->create([
        'started_at' => algiers('2026-11-16 09:00:00'),
        'ended_at' => algiers('2026-11-16 17:00:00'),
    ]);

    AttendanceBreak::factory()->create([
        'session_id' => $session->id,
        'started_at' => algiers('2026-11-16 10:00:00'),
        'ended_at' => algiers('2026-11-16 10:10:00'),
    ]);
    AttendanceBreak::factory()->create([
        'session_id' => $session->id,
        'started_at' => algiers('2026-11-16 13:00:00'),
        'ended_at' => algiers('2026-11-16 13:30:00'),
    ]);

    expect(WorkedTime::sessionMinutes($session->refresh()->load('breaks')))->toBe(440);
});

test('an open break at close counts up to the closing time', function () {
    $session = AttendanceSession::factory()->create([
        'started_at' => algiers('2026-11-16 09:00:00'),
        'ended_at' => algiers('2026-11-16 17:00:00'),
    ]);

    AttendanceBreak::factory()->open()->create([
        'session_id' => $session->id,
        'started_at' => algiers('2026-11-16 16:50:00'),
    ]);

    expect(WorkedTime::sessionMinutes($session->refresh()->load('breaks')))->toBe(470);
});

test('an open session is counted up to the given moment', function () {
    $session = AttendanceSession::factory()->open()->create([
        'started_at' => algiers('2026-11-16 09:00:00'),
    ]);

    expect(WorkedTime::sessionMinutes($session, algiers('2026-11-16 12:00:00')))->toBe(180);
});

test('today totals use the Algiers day and subtract the paid break allowance once', function () {
    CarbonImmutable::setTestNow(algiers('2026-11-16 18:00:00'));

    $employee = Employee::factory()->create();

    $today = AttendanceSession::factory()->create([
        'employee_id' => $employee->id,
        'started_at' => algiers('2026-11-16 09:00:00'),
        'ended_at' => algiers('2026-11-16 17:00:00'),
    ]);
    AttendanceBreak::factory()->create([
        'session_id' => $today->id,
        'started_at' => algiers('2026-11-16 12:00:00'),
        'ended_at' => algiers('2026-11-16 12:30:00'),
    ]);

    AttendanceSession::factory()->create([
        'employee_id' => $employee->id,
        'started_at' => algiers('2026-11-15 09:00:00'),
        'ended_at' => algiers('2026-11-15 17:00:00'),
    ]);

    expect(WorkedTime::todayMinutes($employee, paidBreakAllowanceMinutes: 0))->toBe(450)
        ->and(WorkedTime::todayMinutes($employee, paidBreakAllowanceMinutes: 15))->toBe(465)
        ->and(WorkedTime::format(465))->toBe('7h 45m')
        ->and(WorkedTime::format(0))->toBe('0m');
});
