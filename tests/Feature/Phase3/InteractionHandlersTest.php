<?php

use App\Discord\InteractionResponse;
use App\Jobs\SendAttendanceEvent;
use App\Models\AttendanceBreak;
use App\Models\AttendanceSession;
use App\Models\Employee;
use App\Models\Office;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Support\SignedInteraction;

uses(RefreshDatabase::class);

afterEach(function () {
    CarbonImmutable::setTestNow();
});

function handlerNow(string $time): CarbonImmutable
{
    return CarbonImmutable::parse('2026-11-16 '.$time, 'Africa/Algiers');
}

/**
 * @param  list<array<string, mixed>>  $options
 * @return array<string, mixed>
 */
function attendanceCommand(string $name, string $userId, array $options = []): array
{
    return [
        'id' => '111111111111111111',
        'application_id' => '123456789012345678',
        'type' => 2,
        'guild_id' => '987654321098765432',
        'channel_id' => '555555555555555555',
        'member' => [
            'user' => ['id' => $userId, 'username' => 'karim'],
        ],
        'data' => [
            'id' => '999999999999999999',
            'name' => $name,
            'type' => 1,
            'options' => $options,
        ],
    ];
}

/**
 * @return array<string, mixed>
 */
function backButtonInteraction(string $userId): array
{
    return [
        'id' => '222222222222222222',
        'application_id' => '123456789012345678',
        'type' => 3,
        'guild_id' => '987654321098765432',
        'channel_id' => '555555555555555555',
        'member' => [
            'user' => ['id' => $userId, 'username' => 'karim'],
        ],
        'data' => [
            'custom_id' => 'attendance:back',
            'component_type' => 2,
        ],
    ];
}

function linkedEmployee(string $discordUserId): Employee
{
    $office = Office::factory()->create(['attendance_channel_id' => '777777777777777777']);

    return Employee::factory()->forOffice($office)->linkedTo($discordUserId)->create();
}

test('check-in replies privately with the Algiers time and today total', function () {
    Queue::fake();
    CarbonImmutable::setTestNow(handlerNow('09:00:00'));
    $signer = SignedInteraction::make()->configure();
    $employee = linkedEmployee('9007199254740993');

    $signer->post($this, attendanceCommand('check-in', '9007199254740993'))
        ->assertOk()
        ->assertJsonPath('type', InteractionResponse::TYPE_MESSAGE)
        ->assertJsonPath('data.flags', InteractionResponse::EPHEMERAL)
        ->assertJsonPath('data.content', 'Checked in at 09:00. Today: 0m.');

    expect(AttendanceSession::query()->where('employee_id', $employee->id)->open()->count())->toBe(1);

    Queue::assertPushed(SendAttendanceEvent::class, function (SendAttendanceEvent $job): bool {
        return $job->channelId === '777777777777777777'
            && $job->content === '<@9007199254740993> checked in at 09:00';
    });
});

test('taking a break carries an I am back button and check-in uses it', function () {
    Queue::fake();
    CarbonImmutable::setTestNow(handlerNow('09:00:00'));
    $signer = SignedInteraction::make()->configure();
    $employee = linkedEmployee('444444444444444444');

    $signer->post($this, attendanceCommand('check-in', '444444444444444444'))->assertOk();

    CarbonImmutable::setTestNow(handlerNow('10:00:00'));

    $signer->post($this, attendanceCommand('tmp-check-out', '444444444444444444', [
        ['name' => 'reason', 'type' => 3, 'value' => 'Coffee'],
    ]))
        ->assertOk()
        ->assertJsonPath('data.content', 'Break started at 10:00. Today: 1h 00m.')
        ->assertJsonPath('data.components.0.components.0.custom_id', 'attendance:back')
        ->assertJsonPath('data.components.0.components.0.label', "I'm back");

    CarbonImmutable::setTestNow(handlerNow('10:15:00'));

    $signer->post($this, backButtonInteraction('444444444444444444'))
        ->assertOk()
        ->assertJsonPath('data.content', 'Welcome back at 10:15. Today: 1h 00m.');

    $break = AttendanceBreak::query()->where('session_id', AttendanceSession::query()->where('employee_id', $employee->id)->value('id'))->sole();

    expect($break->ended_at)->not->toBeNull();
});

test('check-in while away says welcome back and closes the break', function () {
    CarbonImmutable::setTestNow(handlerNow('09:00:00'));
    $signer = SignedInteraction::make()->configure();
    linkedEmployee('444444444444444444');

    $signer->post($this, attendanceCommand('check-in', '444444444444444444'))->assertOk();

    CarbonImmutable::setTestNow(handlerNow('10:00:00'));
    $signer->post($this, attendanceCommand('tmp-check-out', '444444444444444444', [
        ['name' => 'reason', 'type' => 3, 'value' => 'Coffee'],
    ]))->assertOk();

    CarbonImmutable::setTestNow(handlerNow('10:20:00'));
    $signer->post($this, attendanceCommand('check-in', '444444444444444444'))
        ->assertOk()
        ->assertJsonPath('data.content', 'Welcome back at 10:20. Today: 1h 00m.');

    expect(AttendanceSession::query()->count())->toBe(1)
        ->and(AttendanceBreak::query()->open()->count())->toBe(0);
});

test('a second check-in gets the original start time privately', function () {
    CarbonImmutable::setTestNow(handlerNow('09:00:00'));
    $signer = SignedInteraction::make()->configure();
    linkedEmployee('444444444444444444');

    $signer->post($this, attendanceCommand('check-in', '444444444444444444'))->assertOk();

    CarbonImmutable::setTestNow(handlerNow('09:05:00'));
    $signer->post($this, attendanceCommand('check-in', '444444444444444444'))
        ->assertOk()
        ->assertJsonPath('data.flags', InteractionResponse::EPHEMERAL)
        ->assertJsonPath('data.content', 'You are already checked in since 09:00.');
});

test('check-out returns the worked total and closes an open break', function () {
    CarbonImmutable::setTestNow(handlerNow('09:00:00'));
    $signer = SignedInteraction::make()->configure();
    linkedEmployee('444444444444444444');

    $signer->post($this, attendanceCommand('check-in', '444444444444444444'))->assertOk();

    CarbonImmutable::setTestNow(handlerNow('12:00:00'));
    $signer->post($this, attendanceCommand('tmp-check-out', '444444444444444444', [
        ['name' => 'reason', 'type' => 3, 'value' => 'Lunch'],
    ]))->assertOk();

    CarbonImmutable::setTestNow(handlerNow('17:00:00'));
    $signer->post($this, attendanceCommand('check-out', '444444444444444444'))
        ->assertOk()
        ->assertJsonPath('data.content', 'Checked out at 17:00. Worked 3h 00m today.');

    expect(AttendanceSession::query()->whereNotNull('ended_at')->count())->toBe(1)
        ->and(AttendanceBreak::query()->open()->count())->toBe(0);
});

test('check-out without an open session is a private error', function () {
    $signer = SignedInteraction::make()->configure();
    linkedEmployee('444444444444444444');

    $signer->post($this, attendanceCommand('check-out', '444444444444444444'))
        ->assertOk()
        ->assertJsonPath('data.flags', InteractionResponse::EPHEMERAL)
        ->assertJsonPath('data.content', 'You have no open session.');
});

test('a break while already away is a private error', function () {
    CarbonImmutable::setTestNow(handlerNow('09:00:00'));
    $signer = SignedInteraction::make()->configure();
    linkedEmployee('444444444444444444');

    $signer->post($this, attendanceCommand('check-in', '444444444444444444'))->assertOk();

    CarbonImmutable::setTestNow(handlerNow('10:00:00'));
    $signer->post($this, attendanceCommand('tmp-check-out', '444444444444444444', [
        ['name' => 'reason', 'type' => 3, 'value' => 'Coffee'],
    ]))->assertOk();

    CarbonImmutable::setTestNow(handlerNow('10:05:00'));
    $signer->post($this, attendanceCommand('tmp-check-out', '444444444444444444', [
        ['name' => 'reason', 'type' => 3, 'value' => 'Again'],
    ]))
        ->assertOk()
        ->assertJsonPath('data.content', 'You are already away since 10:00.');
});

test('an unlinked Discord user is told privately and nothing is recorded', function () {
    $signer = SignedInteraction::make()->configure();

    $signer->post($this, attendanceCommand('check-in', '999999999999999999'))
        ->assertOk()
        ->assertJsonPath('data.flags', InteractionResponse::EPHEMERAL)
        ->assertJsonPath('data.content', 'Your Discord account is not linked to an employee. Ask the owner to link it.');

    expect(AttendanceSession::query()->count())->toBe(0);
});

test('a deactivated employee is told privately and nothing is recorded', function () {
    $signer = SignedInteraction::make()->configure();
    $office = Office::factory()->create();

    Employee::factory()->forOffice($office)->linkedTo('444444444444444444')->inactive()->create();

    $signer->post($this, attendanceCommand('check-in', '444444444444444444'))
        ->assertOk()
        ->assertJsonPath('data.flags', InteractionResponse::EPHEMERAL)
        ->assertJsonPath('data.content', 'Your employee record is deactivated. Nothing was recorded.');

    expect(AttendanceSession::query()->count())->toBe(0);
});
