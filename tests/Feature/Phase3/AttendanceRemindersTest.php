<?php

use App\Enums\AttendanceReviewStatus;
use App\Enums\AttendanceStatus;
use App\Enums\LeaveStatus;
use App\Enums\LeaveType;
use App\Jobs\SendDiscordDm;
use App\Models\AttendanceBreak;
use App\Models\AttendanceSession;
use App\Models\AttendanceSetting;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\LeaveRequest;
use App\Models\Office;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

afterEach(function () {
    CarbonImmutable::setTestNow();
});

function remindCalAt(string $dateTime): CarbonImmutable
{
    return CarbonImmutable::parse($dateTime, 'Africa/Algiers');
}

function remindEmployee(string $discordUserId, array $overrides = []): Employee
{
    $office = Office::factory()->create(['attendance_channel_id' => '777777777777777777']);

    return Employee::factory()->forOffice($office)->linkedTo($discordUserId)->create($overrides);
}

test('an employee who has not checked in gets one reminder after the grace period', function () {
    Queue::fake();
    remindEmployee('444444444444444444', [
        'schedule_start' => '09:00:00',
        'schedule_end' => '17:00:00',
    ]);

    CarbonImmutable::setTestNow(remindCalAt('2026-11-16 09:20:00'));
    expect(Artisan::call('attendance:remind'))->toBe(0);
    Queue::assertNotPushed(SendDiscordDm::class);

    CarbonImmutable::setTestNow(remindCalAt('2026-11-16 09:35:00'));
    expect(Artisan::call('attendance:remind'))->toBe(0);

    Queue::assertPushed(SendDiscordDm::class, fn (SendDiscordDm $job): bool => $job->userId === '444444444444444444'
        && str_contains($job->content, 'expected to check in at 09:00')
        && $job->fallbackChannelId === '777777777777777777'
        && str_starts_with((string) $job->fallbackContent, '<@444444444444444444>'));

    CarbonImmutable::setTestNow(remindCalAt('2026-11-16 09:40:00'));
    expect(Artisan::call('attendance:remind'))->toBe(0);
    Queue::assertPushed(SendDiscordDm::class, 1);
});

test('the missing check-in reminder respects global and per-employee switches', function () {
    Queue::fake();
    $employee = remindEmployee('444444444444444444', [
        'schedule_start' => '09:00:00',
        'schedule_end' => '17:00:00',
    ]);

    CarbonImmutable::setTestNow(remindCalAt('2026-11-16 09:35:00'));

    $employee->update(['reminders_enabled' => false]);
    expect(Artisan::call('attendance:remind'))->toBe(0);
    Queue::assertNotPushed(SendDiscordDm::class);

    $employee->update(['reminders_enabled' => true]);
    AttendanceSetting::current()->update(['reminders_enabled' => false]);
    expect(Artisan::call('attendance:remind'))->toBe(0);
    Queue::assertNotPushed(SendDiscordDm::class);
});

test('there is no missing check-in reminder on a holiday, approved leave or the weekend', function () {
    Queue::fake();
    $holidayDate = '2026-11-16';
    $holidayEmployee = remindEmployee('444444444444444444', [
        'schedule_start' => '09:00:00',
        'schedule_end' => '17:00:00',
    ]);
    $leaveEmployee = remindEmployee('555555555555555555', [
        'schedule_start' => '09:00:00',
        'schedule_end' => '17:00:00',
    ]);
    $weekendEmployee = remindEmployee('666666666666666666', [
        'schedule_start' => '09:00:00',
        'schedule_end' => '17:00:00',
    ]);

    Holiday::factory()->create(['date' => $holidayDate, 'label' => 'Holiday']);
    LeaveRequest::factory()->approved()->create([
        'employee_id' => $leaveEmployee->id,
        'type' => LeaveType::Paid,
        'from' => $holidayDate,
        'to' => $holidayDate,
        'status' => LeaveStatus::Approved,
    ]);

    CarbonImmutable::setTestNow(remindCalAt('2026-11-16 09:35:00'));
    expect(Artisan::call('attendance:remind'))->toBe(0);

    Queue::assertNotPushed(SendDiscordDm::class, fn (SendDiscordDm $job): bool => $job->userId === $holidayEmployee->discord_user_id);
    Queue::assertNotPushed(SendDiscordDm::class, fn (SendDiscordDm $job): bool => $job->userId === $leaveEmployee->discord_user_id);

    CarbonImmutable::setTestNow(remindCalAt('2026-11-20 09:35:00'));
    expect(Artisan::call('attendance:remind'))->toBe(0);
    Queue::assertNotPushed(SendDiscordDm::class, fn (SendDiscordDm $job): bool => $job->userId === $weekendEmployee->discord_user_id);
});

test('an employee who is away too long gets one reminder per break', function () {
    Queue::fake();
    $employee = remindEmployee('444444444444444444');

    $session = AttendanceSession::factory()->create([
        'employee_id' => $employee->id,
        'started_at' => remindCalAt('2026-11-16 09:00:00')->utc(),
        'ended_at' => null,
        'status' => AttendanceStatus::Open,
        'review_status' => AttendanceReviewStatus::Ok,
    ]);
    $break = AttendanceBreak::factory()->open()->create([
        'session_id' => $session->id,
        'started_at' => remindCalAt('2026-11-16 10:00:00')->utc(),
    ]);

    CarbonImmutable::setTestNow(remindCalAt('2026-11-16 11:29:00'));
    expect(Artisan::call('attendance:remind'))->toBe(0);
    Queue::assertNotPushed(SendDiscordDm::class);

    CarbonImmutable::setTestNow(remindCalAt('2026-11-16 11:31:00'));
    expect(Artisan::call('attendance:remind'))->toBe(0);

    Queue::assertPushed(SendDiscordDm::class, fn (SendDiscordDm $job): bool => $job->userId === '444444444444444444'
        && str_contains($job->content, 'away since 10:00'));

    expect($break->refresh()->away_reminder_sent_at)->not->toBeNull();

    expect(Artisan::call('attendance:remind'))->toBe(0);
    Queue::assertPushed(SendDiscordDm::class, 1);
});
