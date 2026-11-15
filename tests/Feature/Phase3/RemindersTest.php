<?php

use App\Actions\Attendance\StartSession;
use App\Enums\AttendanceReviewStatus;
use App\Enums\AttendanceStatus;
use App\Jobs\SendDiscordDm;
use App\Models\AttendanceSession;
use App\Models\AttendanceSetting;
use App\Models\Employee;
use App\Models\Office;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

afterEach(function () {
    CarbonImmutable::setTestNow();
});

function reminderAt(string $dateTime): CarbonImmutable
{
    return CarbonImmutable::parse($dateTime, 'Africa/Algiers');
}

function linkedEmployeeWithChannel(string $discordUserId): Employee
{
    $office = Office::factory()->create(['attendance_channel_id' => '777777777777777777']);

    return Employee::factory()->forOffice($office)->linkedTo($discordUserId)->create();
}

test('the morning command DMs the employee once with a correction button', function () {
    Queue::fake();
    CarbonImmutable::setTestNow(reminderAt('2026-11-17 08:00:00'));
    $employee = linkedEmployeeWithChannel('444444444444444444');

    $session = AttendanceSession::factory()->autoClosed()->create([
        'employee_id' => $employee->id,
        'started_at' => reminderAt('2026-11-16 09:00:00')->utc(),
        'ended_at' => reminderAt('2026-11-16 17:00:00')->utc(),
    ]);

    $this->artisan('attendance:correction-reminders')
        ->expectsOutput('Sent 1 correction request(s).')
        ->assertSuccessful();

    Queue::assertPushed(SendDiscordDm::class, function (SendDiscordDm $job) use ($session): bool {
        return $job->userId === '444444444444444444'
            && str_contains($job->content, 'closed automatically at 17:00')
            && $job->components[0]['components'][0]['custom_id'] === 'attendance:correct:'.$session->id;
    });

    expect($session->refresh()->correction_dm_sent_at)->not->toBeNull();

    $this->artisan('attendance:correction-reminders')->assertSuccessful();
    Queue::assertPushed(SendDiscordDm::class, 1);
});

test('the morning command skips sessions that already have a pending correction', function () {
    Queue::fake();
    CarbonImmutable::setTestNow(reminderAt('2026-11-17 08:00:00'));
    $employee = linkedEmployeeWithChannel('444444444444444444');
    $session = AttendanceSession::factory()->autoClosed()->create([
        'employee_id' => $employee->id,
        'started_at' => reminderAt('2026-11-16 09:00:00')->utc(),
        'ended_at' => reminderAt('2026-11-16 17:00:00')->utc(),
    ]);

    $session->correctionRequests()->create([
        'employee_id' => $employee->id,
        'proposed_ended_at' => reminderAt('2026-11-16 18:00:00')->utc(),
        'status' => 'pending',
    ]);

    $this->artisan('attendance:correction-reminders')->assertSuccessful();

    Queue::assertNotPushed(SendDiscordDm::class);
});

test('an employee working for more than ten hours gets one reminder', function () {
    Queue::fake();
    CarbonImmutable::setTestNow(reminderAt('2026-11-16 09:00:00'));
    $employee = linkedEmployeeWithChannel('444444444444444444');
    app(StartSession::class)->handle($employee);

    CarbonImmutable::setTestNow(reminderAt('2026-11-16 20:00:00'));

    expect(Artisan::call('attendance:remind'))->toBe(0);

    Queue::assertPushed(SendDiscordDm::class, fn (SendDiscordDm $job): bool => $job->userId === '444444444444444444'
        && str_contains($job->content, 'over 10 hours'));

    expect(Artisan::call('attendance:remind'))->toBe(0);
    Queue::assertPushed(SendDiscordDm::class, 1);
});

test('the long-session reminder respects the global and per-employee switches', function () {
    Queue::fake();
    CarbonImmutable::setTestNow(reminderAt('2026-11-16 09:00:00'));
    $employee = linkedEmployeeWithChannel('444444444444444444');
    $employee->update(['reminders_enabled' => false]);
    app(StartSession::class)->handle($employee);

    CarbonImmutable::setTestNow(reminderAt('2026-11-16 20:00:00'));
    expect(Artisan::call('attendance:remind'))->toBe(0);
    Queue::assertNotPushed(SendDiscordDm::class);

    $employee->update(['reminders_enabled' => true]);
    AttendanceSetting::current()->update(['reminders_enabled' => false]);
    expect(Artisan::call('attendance:remind'))->toBe(0);
    Queue::assertNotPushed(SendDiscordDm::class);
});

test('a session under ten hours is not reminded', function () {
    Queue::fake();
    CarbonImmutable::setTestNow(reminderAt('2026-11-16 09:00:00'));
    $employee = linkedEmployeeWithChannel('444444444444444444');
    app(StartSession::class)->handle($employee);

    CarbonImmutable::setTestNow(reminderAt('2026-11-16 18:00:00'));
    expect(Artisan::call('attendance:remind'))->toBe(0);

    Queue::assertNotPushed(SendDiscordDm::class);
    expect(AttendanceSession::query()->sole()->status)->toBe(AttendanceStatus::Open)
        ->and(AttendanceSession::query()->sole()->review_status)->toBe(AttendanceReviewStatus::Ok);
});
