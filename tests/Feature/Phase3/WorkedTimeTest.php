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

test('the paid break allowance applies to each day, not once to the whole range', function () {
    CarbonImmutable::setTestNow(algiers('2026-11-20 18:00:00'));

    $employee = Employee::factory()->create();

    foreach (['2026-11-15', '2026-11-16', '2026-11-17'] as $day) {
        $session = AttendanceSession::factory()->create([
            'employee_id' => $employee->id,
            'started_at' => algiers($day.' 09:00:00'),
            'ended_at' => algiers($day.' 17:00:00'),
        ]);
        AttendanceBreak::factory()->create([
            'session_id' => $session->id,
            'started_at' => algiers($day.' 12:00:00'),
            'ended_at' => algiers($day.' 12:45:00'),
        ]);
    }

    $from = algiers('2026-11-15 00:00:00');
    $to = algiers('2026-11-17 23:59:59');

    // 8h gross, 45 min break, 30 min paid each day: 15 min unpaid per day.
    expect(WorkedTime::rangeMinutes($employee, $from, $to, paidBreakAllowanceMinutes: 30))->toBe(3 * 465)
        ->and(WorkedTime::rangeMinutes($employee, $from, $to, paidBreakAllowanceMinutes: 0))->toBe(3 * 435)
        ->and(WorkedTime::rangeMinutes($employee, $from, $to, paidBreakAllowanceMinutes: 60))->toBe(3 * 480);
});

test('unused paid break allowance on one day does not carry over to another', function () {
    CarbonImmutable::setTestNow(algiers('2026-11-20 18:00:00'));

    $employee = Employee::factory()->create();

    $short = AttendanceSession::factory()->create([
        'employee_id' => $employee->id,
        'started_at' => algiers('2026-11-15 09:00:00'),
        'ended_at' => algiers('2026-11-15 17:00:00'),
    ]);
    AttendanceBreak::factory()->create([
        'session_id' => $short->id,
        'started_at' => algiers('2026-11-15 12:00:00'),
        'ended_at' => algiers('2026-11-15 12:10:00'),
    ]);

    $long = AttendanceSession::factory()->create([
        'employee_id' => $employee->id,
        'started_at' => algiers('2026-11-16 09:00:00'),
        'ended_at' => algiers('2026-11-16 17:00:00'),
    ]);
    AttendanceBreak::factory()->create([
        'session_id' => $long->id,
        'started_at' => algiers('2026-11-16 12:00:00'),
        'ended_at' => algiers('2026-11-16 13:00:00'),
    ]);

    // Day one: break fully paid (480). Day two: 60 min break, 30 paid (450).
    expect(WorkedTime::rangeMinutes(
        $employee,
        algiers('2026-11-15 00:00:00'),
        algiers('2026-11-16 23:59:59'),
        paidBreakAllowanceMinutes: 30,
    ))->toBe(480 + 450);
});

test('the batched today total matches the per-employee total', function () {
    CarbonImmutable::setTestNow(algiers('2026-11-16 18:00:00'));

    $withSession = Employee::factory()->create();
    $without = Employee::factory()->create();

    $session = AttendanceSession::factory()->create([
        'employee_id' => $withSession->id,
        'started_at' => algiers('2026-11-16 09:00:00'),
        'ended_at' => algiers('2026-11-16 17:00:00'),
    ]);
    AttendanceBreak::factory()->create([
        'session_id' => $session->id,
        'started_at' => algiers('2026-11-16 12:00:00'),
        'ended_at' => algiers('2026-11-16 12:30:00'),
    ]);

    $batched = WorkedTime::todayMinutesForEmployees([$withSession->id, $without->id], paidBreakAllowanceMinutes: 15);

    expect($batched)->toBe([
        $withSession->id => WorkedTime::todayMinutes($withSession, paidBreakAllowanceMinutes: 15),
        $without->id => 0,
    ])->and($batched[$withSession->id])->toBe(465);
});
