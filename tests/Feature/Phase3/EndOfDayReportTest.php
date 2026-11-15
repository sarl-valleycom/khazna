<?php

use App\Discord\InteractionResponse;
use App\Enums\AttendanceReviewStatus;
use App\Enums\AttendanceStatus;
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

function reportAt(string $time): CarbonImmutable
{
    return CarbonImmutable::parse('2026-11-16 '.$time, 'Africa/Algiers');
}

function reportEmployee(string $discordUserId): Employee
{
    $office = Office::factory()->create(['attendance_channel_id' => '777777777777777777']);

    return Employee::factory()->forOffice($office)->linkedTo($discordUserId)->create();
}

/**
 * @param  list<array<string, mixed>>  $options
 * @return array<string, mixed>
 */
function reportCommand(string $name, string $userId, array $options = []): array
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

/**
 * @return array<string, mixed>
 */
function reportButton(string $userId, int $sessionId): array
{
    return [
        'id' => '333333333333333333',
        'application_id' => '123456789012345678',
        'type' => 3,
        'guild_id' => '987654321098765432',
        'member' => ['user' => ['id' => $userId, 'username' => 'karim']],
        'data' => ['custom_id' => 'attendance:report:'.$sessionId, 'component_type' => 2],
    ];
}

/**
 * @return array<string, mixed>
 */
function reportModal(string $userId, int $sessionId, string $report, ?string $blockers = null): array
{
    $components = [
        ['type' => 4, 'custom_id' => 'report', 'value' => $report],
    ];

    if ($blockers !== null) {
        $components[] = ['type' => 4, 'custom_id' => 'blockers', 'value' => $blockers];
    }

    return [
        'id' => '444444444444444444',
        'application_id' => '123456789012345678',
        'type' => 5,
        'guild_id' => '987654321098765432',
        'member' => ['user' => ['id' => $userId, 'username' => 'karim']],
        'data' => [
            'custom_id' => 'attendance:report:'.$sessionId,
            'components' => [['type' => 1, 'components' => $components]],
        ],
    ];
}

function reportSession(Employee $employee, bool $open = false): AttendanceSession
{
    return AttendanceSession::factory()->create([
        'employee_id' => $employee->id,
        'started_at' => reportAt('09:00:00')->utc(),
        'ended_at' => $open ? null : reportAt('17:00:00')->utc(),
        'status' => $open ? AttendanceStatus::Open : AttendanceStatus::Closed,
        'review_status' => AttendanceReviewStatus::Ok,
    ]);
}

test('check-out offers the end-of-day report button', function () {
    Queue::fake();
    CarbonImmutable::setTestNow(reportAt('09:00:00'));
    $signer = SignedInteraction::make()->configure();
    $employee = reportEmployee('444444444444444444');

    $signer->post($this, reportCommand('check-in', '444444444444444444'))->assertOk();

    CarbonImmutable::setTestNow(reportAt('17:00:00'));

    $session = AttendanceSession::query()->where('employee_id', $employee->id)->sole();

    $signer->post($this, reportCommand('check-out', '444444444444444444'))
        ->assertOk()
        ->assertJsonPath('data.content', 'Checked out at 17:00. Worked 8h 00m today.')
        ->assertJsonPath('data.components.0.components.0.custom_id', 'attendance:report:'.$session->id)
        ->assertJsonPath('data.components.0.components.0.label', "Write today's report");

    expect($session->refresh()->ended_at)->not->toBeNull();
});

test('the report button opens a modal with a required report and optional blockers', function () {
    $signer = SignedInteraction::make()->configure();
    $employee = reportEmployee('444444444444444444');
    $session = reportSession($employee);

    $signer->post($this, reportButton('444444444444444444', $session->id))
        ->assertOk()
        ->assertJsonPath('type', InteractionResponse::TYPE_MODAL)
        ->assertJsonPath('data.custom_id', 'attendance:report:'.$session->id)
        ->assertJsonPath('data.components.0.components.0.custom_id', 'report')
        ->assertJsonPath('data.components.0.components.0.required', true)
        ->assertJsonPath('data.components.1.components.0.custom_id', 'blockers')
        ->assertJsonPath('data.components.1.components.0.required', false);
});

test('submitting the report saves it on the closed session', function () {
    $signer = SignedInteraction::make()->configure();
    $employee = reportEmployee('444444444444444444');
    $session = reportSession($employee);

    $signer->post($this, reportModal(
        '444444444444444444',
        $session->id,
        'Reviewed the new client file.',
        'Waiting on the signed contract.',
    ))
        ->assertOk()
        ->assertJsonPath('data.flags', InteractionResponse::EPHEMERAL)
        ->assertJsonPath('data.content', 'Thanks, your report is saved.');

    expect($session->refresh()->report)->toBe('Reviewed the new client file.')
        ->and($session->blockers)->toBe('Waiting on the signed contract.')
        ->and($session->status)->toBe(AttendanceStatus::Closed);
});

test('blockers are optional and blank text is stored as null', function () {
    $signer = SignedInteraction::make()->configure();
    $employee = reportEmployee('444444444444444444');
    $session = reportSession($employee);

    $signer->post($this, reportModal('444444444444444444', $session->id, 'Finished the audit.', '   '))
        ->assertOk();

    expect($session->refresh()->report)->toBe('Finished the audit.')
        ->and($session->blockers)->toBeNull();
});

test('a blank report is refused and nothing is written', function () {
    $signer = SignedInteraction::make()->configure();
    $employee = reportEmployee('444444444444444444');
    $session = reportSession($employee);

    $signer->post($this, reportModal('444444444444444444', $session->id, '   '))
        ->assertOk()
        ->assertJsonPath('data.content', 'Write what you did today.');

    expect($session->refresh()->report)->toBeNull();
});

test('an employee cannot report on another employee session', function () {
    $signer = SignedInteraction::make()->configure();
    reportEmployee('444444444444444444');
    $other = reportEmployee('555555555555555555');
    $session = reportSession($other);

    $signer->post($this, reportButton('444444444444444444', $session->id))
        ->assertOk()
        ->assertJsonPath('data.content', 'That session was not found.');

    $signer->post($this, reportModal('444444444444444444', $session->id, 'Not mine.'))
        ->assertOk()
        ->assertJsonPath('data.content', 'That session was not found.');

    expect($session->refresh()->report)->toBeNull();
});

test('a report can only be written after checking out', function () {
    $signer = SignedInteraction::make()->configure();
    $employee = reportEmployee('444444444444444444');
    $session = reportSession($employee, open: true);

    $signer->post($this, reportButton('444444444444444444', $session->id))
        ->assertOk()
        ->assertJsonPath('data.content', 'Check out before writing your report.');

    $signer->post($this, reportModal('444444444444444444', $session->id, 'Too early.'))
        ->assertOk()
        ->assertJsonPath('data.content', 'Check out before writing your report.');

    expect($session->refresh()->report)->toBeNull();
});
