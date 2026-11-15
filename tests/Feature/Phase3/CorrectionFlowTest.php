<?php

use App\Discord\InteractionResponse;
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
use Tests\Support\SignedInteraction;

uses(RefreshDatabase::class);

afterEach(function () {
    CarbonImmutable::setTestNow();
});

function correctionAt(string $dateTime): CarbonImmutable
{
    return CarbonImmutable::parse($dateTime, 'Africa/Algiers');
}

function correctionEmployee(string $discordUserId): Employee
{
    $office = Office::factory()->create();

    return Employee::factory()->forOffice($office)->linkedTo($discordUserId)->create();
}

function autoClosedSession(Employee $employee, string $endedAt = '2026-11-16 17:00:00'): AttendanceSession
{
    return AttendanceSession::factory()->autoClosed()->create([
        'employee_id' => $employee->id,
        'started_at' => correctionAt('2026-11-16 09:00:00')->utc(),
        'ended_at' => correctionAt($endedAt)->utc(),
    ]);
}

/**
 * @return array<string, mixed>
 */
function correctionButton(string $userId, int $sessionId): array
{
    return [
        'id' => '333333333333333333',
        'application_id' => '123456789012345678',
        'type' => 3,
        'guild_id' => '987654321098765432',
        'member' => ['user' => ['id' => $userId, 'username' => 'karim']],
        'data' => [
            'custom_id' => 'attendance:correct:'.$sessionId,
            'component_type' => 2,
        ],
    ];
}

/**
 * @return array<string, mixed>
 */
function correctionModal(string $userId, int $sessionId, string $endedAt, string $note = 'Forgot to check out'): array
{
    return [
        'id' => '444444444444444444',
        'application_id' => '123456789012345678',
        'type' => 5,
        'guild_id' => '987654321098765432',
        'member' => ['user' => ['id' => $userId, 'username' => 'karim']],
        'data' => [
            'custom_id' => 'attendance:correct:'.$sessionId,
            'components' => [
                [
                    'type' => 1,
                    'components' => [
                        ['type' => 4, 'custom_id' => 'ended_at', 'value' => $endedAt],
                        ['type' => 4, 'custom_id' => 'note', 'value' => $note],
                    ],
                ],
            ],
        ],
    ];
}

test('the correction button opens a modal with a time and a note field', function () {
    $signer = SignedInteraction::make()->configure();
    $employee = correctionEmployee('444444444444444444');
    $session = autoClosedSession($employee);

    $signer->post($this, correctionButton('444444444444444444', $session->id))
        ->assertOk()
        ->assertJsonPath('type', InteractionResponse::TYPE_MODAL)
        ->assertJsonPath('data.custom_id', 'attendance:correct:'.$session->id)
        ->assertJsonPath('data.components.0.components.0.custom_id', 'ended_at')
        ->assertJsonPath('data.components.1.components.0.custom_id', 'note')
        ->assertJsonPath('data.components.1.components.0.required', false);
});

test('a correction is saved as pending when self-correction is off', function () {
    CarbonImmutable::setTestNow(correctionAt('2026-11-17 08:00:00'));
    $signer = SignedInteraction::make()->configure();
    $employee = correctionEmployee('444444444444444444');
    $session = autoClosedSession($employee);

    AttendanceSetting::current()->update(['self_correction_enabled' => false]);

    $signer->post($this, correctionModal('444444444444444444', $session->id, '18:30'))
        ->assertOk()
        ->assertJsonPath('data.flags', InteractionResponse::EPHEMERAL)
        ->assertJsonPath('data.content', 'Thanks. Your correction is waiting for the owner to approve.');

    $request = CorrectionRequest::query()->sole();

    expect($request->status)->toBe(CorrectionStatus::Pending)
        ->and($request->proposed_ended_at?->toDateTimeString())->toBe('2026-11-16 17:30:00')
        ->and($request->note)->toBe('Forgot to check out')
        ->and($session->refresh()->ended_at?->toDateTimeString())->toBe('2026-11-16 16:00:00')
        ->and($session->review_status)->toBe(AttendanceReviewStatus::NeedsReview);
});

test('self-correction applies immediately when enabled and within 24 hours', function () {
    CarbonImmutable::setTestNow(correctionAt('2026-11-17 08:00:00'));
    $signer = SignedInteraction::make()->configure();
    $employee = correctionEmployee('444444444444444444');
    $session = autoClosedSession($employee);

    AttendanceSetting::current()->update(['self_correction_enabled' => true, 'self_correction_hours' => 24]);

    $signer->post($this, correctionModal('444444444444444444', $session->id, '18:30'))
        ->assertOk()
        ->assertJsonPath('data.content', 'End time corrected to 18:30. Thank you.');

    expect($session->refresh()->ended_at?->toDateTimeString())->toBe('2026-11-16 17:30:00')
        ->and($session->review_status)->toBe(AttendanceReviewStatus::Approved)
        ->and(CorrectionRequest::query()->sole()->status)->toBe(CorrectionStatus::Approved);
});

test('self-correction outside the window still waits for the owner', function () {
    CarbonImmutable::setTestNow(correctionAt('2026-11-17 18:00:00'));
    $signer = SignedInteraction::make()->configure();
    $employee = correctionEmployee('444444444444444444');
    $session = autoClosedSession($employee);

    AttendanceSetting::current()->update(['self_correction_enabled' => true, 'self_correction_hours' => 24]);

    $signer->post($this, correctionModal('444444444444444444', $session->id, '18:30'))->assertOk();

    expect(CorrectionRequest::query()->sole()->status)->toBe(CorrectionStatus::Pending)
        ->and($session->refresh()->review_status)->toBe(AttendanceReviewStatus::NeedsReview);
});

test('a malformed end time is refused before anything is written', function () {
    CarbonImmutable::setTestNow(correctionAt('2026-11-17 08:00:00'));
    $signer = SignedInteraction::make()->configure();
    $employee = correctionEmployee('444444444444444444');
    $session = autoClosedSession($employee);

    $signer->post($this, correctionModal('444444444444444444', $session->id, '25:99'))
        ->assertOk()
        ->assertJsonPath('data.content', 'Use HH:MM in Algiers time.');

    expect(CorrectionRequest::query()->count())->toBe(0);
});

test('an employee cannot correct another employee session', function () {
    CarbonImmutable::setTestNow(correctionAt('2026-11-17 08:00:00'));
    $signer = SignedInteraction::make()->configure();
    $owner = correctionEmployee('444444444444444444');
    $other = correctionEmployee('555555555555555555');
    $session = autoClosedSession($other);

    $signer->post($this, correctionButton('444444444444444444', $session->id))
        ->assertOk()
        ->assertJsonPath('data.content', 'That session was not found.');

    expect($owner->id)->not->toBe($other->id);
});

test('a manually closed session cannot be self-corrected', function () {
    CarbonImmutable::setTestNow(correctionAt('2026-11-17 08:00:00'));
    $signer = SignedInteraction::make()->configure();
    $employee = correctionEmployee('444444444444444444');
    $session = autoClosedSession($employee);
    $session->update(['status' => AttendanceStatus::Closed]);

    AttendanceSetting::current()->update(['self_correction_enabled' => true, 'self_correction_hours' => 24]);

    $signer->post($this, correctionModal('444444444444444444', $session->id, '18:30'))->assertOk();

    expect(CorrectionRequest::query()->sole()->status)->toBe(CorrectionStatus::Pending)
        ->and($session->refresh()->ended_at?->toDateTimeString())->toBe('2026-11-16 16:00:00');
});

test('a self-approved correction cannot be repeated to extend the session again', function () {
    CarbonImmutable::setTestNow(correctionAt('2026-11-17 08:00:00'));
    $signer = SignedInteraction::make()->configure();
    $employee = correctionEmployee('444444444444444444');
    $session = autoClosedSession($employee);

    AttendanceSetting::current()->update(['self_correction_enabled' => true, 'self_correction_hours' => 24]);

    $signer->post($this, correctionModal('444444444444444444', $session->id, '18:30'))->assertOk();

    expect($session->refresh()->ended_at?->toDateTimeString())->toBe('2026-11-16 17:30:00');

    CarbonImmutable::setTestNow(correctionAt('2026-11-17 17:00:00'));

    $signer->post($this, correctionModal('444444444444444444', $session->id, '19:30'))->assertOk();

    expect($session->refresh()->ended_at?->toDateTimeString())->toBe('2026-11-16 17:30:00')
        ->and(CorrectionRequest::query()->where('status', CorrectionStatus::Pending)->count())->toBe(1);
});

test('a self-correction that would make the session longer than 12 hours waits for the owner', function () {
    CarbonImmutable::setTestNow(correctionAt('2026-11-17 08:00:00'));
    $signer = SignedInteraction::make()->configure();
    $employee = correctionEmployee('444444444444444444');
    $session = autoClosedSession($employee);

    AttendanceSetting::current()->update(['self_correction_enabled' => true, 'self_correction_hours' => 24]);

    // Session started 09:00; 22:30 is 13h30 later.
    $signer->post($this, correctionModal('444444444444444444', $session->id, '22:30'))->assertOk();

    expect(CorrectionRequest::query()->sole()->status)->toBe(CorrectionStatus::Pending)
        ->and($session->refresh()->ended_at?->toDateTimeString())->toBe('2026-11-16 16:00:00')
        ->and($session->review_status)->toBe(AttendanceReviewStatus::NeedsReview);
});

test('self-correction is on by default for a fresh installation', function () {
    CarbonImmutable::setTestNow(correctionAt('2026-11-17 08:00:00'));
    $signer = SignedInteraction::make()->configure();
    $employee = correctionEmployee('444444444444444444');
    $session = autoClosedSession($employee);

    expect(AttendanceSetting::current()->self_correction_enabled)->toBeTrue();

    $signer->post($this, correctionModal('444444444444444444', $session->id, '18:30'))
        ->assertOk()
        ->assertJsonPath('data.content', 'End time corrected to 18:30. Thank you.');
});
