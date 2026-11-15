<?php

use App\Discord\InteractionResponse;
use App\Enums\AttendanceReviewStatus;
use App\Enums\AttendanceStatus;
use App\Models\AttendanceBreak;
use App\Models\AttendanceSession;
use App\Models\Employee;
use App\Models\Office;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\SignedInteraction;

uses(RefreshDatabase::class);

afterEach(function () {
    CarbonImmutable::setTestNow();
});

function selfServiceAt(string $dateTime): CarbonImmutable
{
    return CarbonImmutable::parse($dateTime, 'Africa/Algiers');
}

function selfServiceEmployee(string $discordUserId): Employee
{
    $office = Office::factory()->create();

    return Employee::factory()->forOffice($office)->linkedTo($discordUserId)->create();
}

/**
 * @param  list<array<string, mixed>>  $options
 * @return array<string, mixed>
 */
function selfServiceCommand(string $name, string $userId, array $options = []): array
{
    return [
        'id' => '111111111111111111',
        'application_id' => '123456789012345678',
        'type' => 2,
        'guild_id' => '987654321098765432',
        'channel_id' => '555555555555555555',
        'member' => ['user' => ['id' => $userId, 'username' => 'karim']],
        'data' => ['id' => '999999999999999999', 'name' => $name, 'type' => 1, 'options' => $options],
    ];
}

function selfServiceSession(Employee $employee, string $startedAt, ?string $endedAt, ?string $status = null): AttendanceSession
{
    return AttendanceSession::factory()->create([
        'employee_id' => $employee->id,
        'started_at' => selfServiceAt($startedAt)->utc(),
        'ended_at' => $endedAt === null ? null : selfServiceAt($endedAt)->utc(),
        'status' => $status ?? ($endedAt === null ? AttendanceStatus::Open : AttendanceStatus::Closed),
        'review_status' => AttendanceReviewStatus::Ok,
    ]);
}

test('status reports working, away and offline with today total', function () {
    CarbonImmutable::setTestNow(selfServiceAt('2026-11-18 15:00:00'));
    $signer = SignedInteraction::make()->configure();
    $employee = selfServiceEmployee('444444444444444444');
    $session = selfServiceSession($employee, '2026-11-18 09:00:00', null);

    $signer->post($this, selfServiceCommand('status', '444444444444444444'))
        ->assertOk()
        ->assertJsonPath('data.flags', InteractionResponse::EPHEMERAL)
        ->assertJsonPath('data.content', 'You are working since 09:00. Today: 6h 00m.');

    AttendanceBreak::factory()->open()->create([
        'session_id' => $session->id,
        'started_at' => selfServiceAt('2026-11-18 14:00:00')->utc(),
    ]);

    $signer->post($this, selfServiceCommand('status', '444444444444444444'))
        ->assertOk()
        ->assertJsonPath('data.content', 'You are away since 14:00. Today: 5h 00m.');

    $offline = selfServiceEmployee('555555555555555555');

    $signer->post($this, selfServiceCommand('status', '555555555555555555'))
        ->assertOk()
        ->assertJsonPath('data.content', 'You are offline. Today: 0m.');

    expect($offline->id)->not->toBe($employee->id);
});

test('my-hours reports today, week and month totals privately', function () {
    CarbonImmutable::setTestNow(selfServiceAt('2026-11-18 15:00:00'));
    $signer = SignedInteraction::make()->configure();
    $employee = selfServiceEmployee('444444444444444444');

    selfServiceSession($employee, '2026-11-01 09:00:00', '2026-11-01 10:00:00');
    selfServiceSession($employee, '2026-11-15 09:00:00', '2026-11-15 17:00:00');
    selfServiceSession($employee, '2026-11-16 09:00:00', '2026-11-16 17:00:00');
    selfServiceSession($employee, '2026-11-17 09:00:00', '2026-11-17 17:00:00');
    $today = selfServiceSession($employee, '2026-11-18 09:00:00', null);

    AttendanceBreak::factory()->open()->create([
        'session_id' => $today->id,
        'started_at' => selfServiceAt('2026-11-18 14:00:00')->utc(),
    ]);

    $signer->post($this, selfServiceCommand('my-hours', '444444444444444444'))
        ->assertOk()
        ->assertJsonPath('data.flags', InteractionResponse::EPHEMERAL)
        ->assertJsonPath('data.content', 'Today: 5h 00m.');

    $signer->post($this, selfServiceCommand('my-hours', '444444444444444444', [
        ['name' => 'period', 'type' => 3, 'value' => 'week'],
    ]))
        ->assertOk()
        ->assertJsonPath('data.content', 'This week: 29h 00m.');

    $signer->post($this, selfServiceCommand('my-hours', '444444444444444444', [
        ['name' => 'period', 'type' => 3, 'value' => 'month'],
    ]))
        ->assertOk()
        ->assertJsonPath('data.content', 'This month: 30h 00m.');
});

test('status and my-hours are private to the linked employee', function () {
    $signer = SignedInteraction::make()->configure();

    $signer->post($this, selfServiceCommand('status', '999999999999999999'))
        ->assertOk()
        ->assertJsonPath('data.flags', InteractionResponse::EPHEMERAL)
        ->assertJsonPath('data.content', 'Your Discord account is not linked to an employee. Ask the owner to link it.');

    $signer->post($this, selfServiceCommand('my-hours', '999999999999999999'))
        ->assertOk()
        ->assertJsonPath('data.content', 'Your Discord account is not linked to an employee. Ask the owner to link it.');
});
