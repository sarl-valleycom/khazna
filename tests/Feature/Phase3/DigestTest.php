<?php

use App\Enums\AttendanceReviewStatus;
use App\Enums\AttendanceStatus;
use App\Enums\LeaveStatus;
use App\Enums\LeaveType;
use App\Models\AttendanceBreak;
use App\Models\AttendanceSession;
use App\Models\AttendanceSetting;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\Office;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

afterEach(function () {
    CarbonImmutable::setTestNow();
});

function digestAt(string $dateTime): CarbonImmutable
{
    return CarbonImmutable::parse($dateTime, 'Africa/Algiers');
}

function digestConfig(): void
{
    config()->set([
        'discord.api_base' => 'https://discord.test/api/v10',
        'discord.bot_token' => 'test-bot-token',
        'discord.timeout' => 5,
    ]);

    AttendanceSetting::current()->update(['owner_digest_channel_id' => '888888888888888888']);
}

function digestEmployee(Office $office, string $discordUserId, string $name): Employee
{
    return Employee::factory()->forOffice($office)->linkedTo($discordUserId)->create([
        'name' => $name,
        'schedule_start' => '09:00:00',
        'schedule_end' => '17:00:00',
    ]);
}

test('the daily digest groups in, late, absent, away, forgotten and reports by office', function () {
    CarbonImmutable::setTestNow(digestAt('2026-11-16 18:00:00'));
    digestConfig();
    $office = Office::factory()->create(['name' => 'Algiers']);

    $in = digestEmployee($office, '111111111111111111', 'In');
    $away = digestEmployee($office, '222222222222222222', 'Away');
    $late = digestEmployee($office, '333333333333333333', 'Late');
    $absent = digestEmployee($office, '444444444444444444', 'Absent');
    $forgotten = digestEmployee($office, '555555555555555555', 'Forgotten');
    $onLeave = digestEmployee($office, '666666666666666666', 'Leave');

    AttendanceSession::factory()->create([
        'employee_id' => $in->id,
        'started_at' => digestAt('2026-11-16 09:00:00')->utc(),
        'ended_at' => null,
        'status' => AttendanceStatus::Open,
        'review_status' => AttendanceReviewStatus::Ok,
    ]);

    $awaySession = AttendanceSession::factory()->create([
        'employee_id' => $away->id,
        'started_at' => digestAt('2026-11-16 09:00:00')->utc(),
        'ended_at' => null,
        'status' => AttendanceStatus::Open,
        'review_status' => AttendanceReviewStatus::Ok,
    ]);
    AttendanceBreak::factory()->open()->create([
        'session_id' => $awaySession->id,
        'started_at' => digestAt('2026-11-16 10:00:00')->utc(),
    ]);

    AttendanceSession::factory()->create([
        'employee_id' => $late->id,
        'started_at' => digestAt('2026-11-16 09:20:00')->utc(),
        'ended_at' => digestAt('2026-11-16 17:00:00')->utc(),
        'status' => AttendanceStatus::Closed,
        'review_status' => AttendanceReviewStatus::Ok,
        'report' => 'Finished the monthly report.',
        'blockers' => 'None. ',
    ]);

    AttendanceSession::factory()->autoClosed()->create([
        'employee_id' => $forgotten->id,
        'started_at' => digestAt('2026-11-16 09:00:00')->utc(),
        'ended_at' => digestAt('2026-11-16 17:00:00')->utc(),
    ]);

    LeaveRequest::factory()->approved()->create([
        'employee_id' => $onLeave->id,
        'type' => LeaveType::Paid,
        'from' => '2026-11-16',
        'to' => '2026-11-16',
        'status' => LeaveStatus::Approved,
    ]);

    Http::fake(['discord.test/*' => Http::response([], 200)]);

    $this->artisan('attendance:digest-daily')
        ->expectsOutput('Daily attendance digest posted.')
        ->assertSuccessful();

    Http::assertSent(function (Request $request): bool {
        $embed = $request->data()['embeds'][0] ?? [];
        $value = collect($embed['fields'] ?? [])->firstWhere('name', 'Algiers')['value'] ?? '';

        return $request->url() === 'https://discord.test/api/v10/channels/888888888888888888/messages'
            && str_contains($value, '**In**')
            && str_contains($value, '<@111111111111111111> (since 09:00)')
            && str_contains($value, '**Late**')
            && str_contains($value, '<@333333333333333333> — 09:20 (expected 09:15)')
            && str_contains($value, '**Absent**')
            && str_contains($value, '<@444444444444444444>')
            && str_contains($value, '**Away**')
            && str_contains($value, '<@222222222222222222> (since 09:00)')
            && str_contains($value, '**Forgotten**')
            && str_contains($value, '<@555555555555555555> — closed automatically at 17:00')
            && str_contains($value, '**Reports**')
            && str_contains($value, 'Finished the monthly report.')
            && ! str_contains($value, '<@666666666666666666>');
    });
});

test('the daily digest skips cleanly without an owner channel', function () {
    CarbonImmutable::setTestNow(digestAt('2026-11-16 18:00:00'));

    $this->artisan('attendance:digest-daily')
        ->expectsOutput('No owner digest channel is configured.')
        ->assertSuccessful();

    Http::assertNothingSent();
});

test('the weekly digest compares worked and expected hours, lateness and overtime', function () {
    CarbonImmutable::setTestNow(digestAt('2026-11-22 08:00:00'));
    digestConfig();
    $office = Office::factory()->create(['name' => 'Algiers']);
    $employee = digestEmployee($office, '111111111111111111', 'Alice');

    AttendanceSession::factory()->create([
        'employee_id' => $employee->id,
        'started_at' => digestAt('2026-11-15 09:20:00')->utc(),
        'ended_at' => digestAt('2026-11-15 17:00:00')->utc(),
        'status' => AttendanceStatus::Closed,
        'review_status' => AttendanceReviewStatus::Ok,
    ]);
    AttendanceSession::factory()->create([
        'employee_id' => $employee->id,
        'started_at' => digestAt('2026-11-16 09:00:00')->utc(),
        'ended_at' => digestAt('2026-11-16 17:00:00')->utc(),
        'status' => AttendanceStatus::Closed,
        'review_status' => AttendanceReviewStatus::Ok,
    ]);

    Http::fake(['discord.test/*' => Http::response([], 200)]);

    $this->artisan('attendance:digest-weekly')
        ->expectsOutput('Weekly attendance digest posted.')
        ->assertSuccessful();

    Http::assertSent(function (Request $request): bool {
        $embed = $request->data()['embeds'][0] ?? [];
        $value = collect($embed['fields'] ?? [])->firstWhere('name', 'Algiers')['value'] ?? '';

        return $request->url() === 'https://discord.test/api/v10/channels/888888888888888888/messages'
            && str_contains((string) ($embed['title'] ?? ''), 'Weekly attendance')
            && str_contains($value, '<@111111111111111111> — 15h 40m / 40h 00m · late 1 · overtime 0m');
    });
});

test('the weekly digest counts Saturday work for an employee who works Saturdays', function () {
    CarbonImmutable::setTestNow(digestAt('2026-11-22 08:00:00'));
    digestConfig();
    $office = Office::factory()->create(['name' => 'Algiers']);
    $employee = Employee::factory()->forOffice($office)->linkedTo('222222222222222222')->scheduled('09:00:00', '17:00:00', 480, [0, 1, 2, 3, 4, 6])->create([
        'name' => 'Sara',
    ]);

    // Saturday 21 Nov, eight hours.
    AttendanceSession::factory()->create([
        'employee_id' => $employee->id,
        'started_at' => digestAt('2026-11-21 09:00:00')->utc(),
        'ended_at' => digestAt('2026-11-21 17:00:00')->utc(),
        'status' => AttendanceStatus::Closed,
        'review_status' => AttendanceReviewStatus::Ok,
    ]);

    Http::fake(['discord.test/*' => Http::response([], 200)]);

    $this->artisan('attendance:digest-weekly')->assertSuccessful();

    Http::assertSent(function (Request $request): bool {
        $embed = $request->data()['embeds'][0] ?? [];
        $value = collect($embed['fields'] ?? [])->firstWhere('name', 'Algiers')['value'] ?? '';

        // Six working days expected (48h), eight worked on the Saturday.
        return str_contains($value, '<@222222222222222222> — 8h 00m / 48h 00m');
    });
});

test('the weekly digest subtracts the paid break allowance per day', function () {
    CarbonImmutable::setTestNow(digestAt('2026-11-22 08:00:00'));
    digestConfig();
    AttendanceSetting::current()->update(['paid_break_minutes' => 30]);
    $office = Office::factory()->create(['name' => 'Algiers']);
    $employee = digestEmployee($office, '333333333333333333', 'Omar');

    foreach (['2026-11-15', '2026-11-16'] as $day) {
        $session = AttendanceSession::factory()->create([
            'employee_id' => $employee->id,
            'started_at' => digestAt($day.' 09:00:00')->utc(),
            'ended_at' => digestAt($day.' 17:00:00')->utc(),
            'status' => AttendanceStatus::Closed,
            'review_status' => AttendanceReviewStatus::Ok,
        ]);
        AttendanceBreak::factory()->create([
            'session_id' => $session->id,
            'started_at' => digestAt($day.' 12:00:00')->utc(),
            'ended_at' => digestAt($day.' 12:30:00')->utc(),
        ]);
    }

    Http::fake(['discord.test/*' => Http::response([], 200)]);

    $this->artisan('attendance:digest-weekly')->assertSuccessful();

    // 30 minute breaks are fully paid on both days: 2 x 8h.
    Http::assertSent(function (Request $request): bool {
        $embed = $request->data()['embeds'][0] ?? [];
        $value = collect($embed['fields'] ?? [])->firstWhere('name', 'Algiers')['value'] ?? '';

        return str_contains($value, '<@333333333333333333> — 16h 00m / 40h 00m');
    });
});
