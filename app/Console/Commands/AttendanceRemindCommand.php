<?php

namespace App\Console\Commands;

use App\Attendance\WorkCalendar;
use App\Enums\EmployeeStatus;
use App\Jobs\SendDiscordDm;
use App\Models\AttendanceBreak;
use App\Models\AttendanceReminder;
use App\Models\AttendanceSession;
use App\Models\AttendanceSetting;
use App\Models\Employee;
use App\Support\AlgerianDate;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class AttendanceRemindCommand extends Command
{
    protected $signature = 'attendance:remind';

    protected $description = 'DM employees who are late, away too long, or still working after ten hours';

    public function __construct(private readonly WorkCalendar $calendar)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $settings = AttendanceSetting::current();

        if (! $settings->reminders_enabled) {
            $this->info('Reminders are disabled.');

            return self::SUCCESS;
        }

        $now = AlgerianDate::now();
        $sent = 0;

        Employee::query()
            ->where('status', EmployeeStatus::Active)
            ->where('reminders_enabled', true)
            ->whereNotNull('discord_user_id')
            ->with('office')
            ->orderBy('id')
            ->chunkById(100, function ($employees) use ($settings, $now, &$sent): void {
                foreach ($employees as $employee) {
                    $session = AttendanceSession::query()
                        ->where('employee_id', $employee->id)
                        ->open()
                        ->with('breaks')
                        ->latest('started_at')
                        ->first();

                    if ($session !== null) {
                        if ($this->remindAway($employee, $session, $settings, $now)) {
                            $sent++;
                        }

                        if ($this->remindLongSession($employee, $session, $settings, $now)) {
                            $sent++;
                        }

                        continue;
                    }

                    if ($this->remindMissingCheckIn($employee, $settings, $now)) {
                        $sent++;
                    }
                }
            });

        $this->info("Sent {$sent} reminder(s).");

        return self::SUCCESS;
    }

    private function remindMissingCheckIn(Employee $employee, AttendanceSetting $settings, CarbonImmutable $now): bool
    {
        if ($employee->schedule_start === null || $employee->schedule_start === '') {
            return false;
        }

        $today = $now->toDateString();
        $start = CarbonImmutable::parse($today.' '.$employee->schedule_start, AlgerianDate::TZ);
        $end = $employee->schedule_end !== null && $employee->schedule_end !== ''
            ? CarbonImmutable::parse($today.' '.$employee->schedule_end, AlgerianDate::TZ)
            : $start->addMinutes($employee->expected_minutes_per_day);

        if ($end->lte($start)) {
            $end = $end->addDay();
        }

        if ($now->lt($start->addMinutes($settings->reminder_after_minutes)) || $now->gte($end)) {
            return false;
        }

        if (! $this->calendar->isWorkingDay($employee, $now)) {
            return false;
        }

        [$from, $to] = AlgerianDate::dayRangeUtc($today);

        if ($employee->attendanceSessions()->whereBetween('started_at', [$from, $to])->exists()) {
            return false;
        }

        $reminder = AttendanceReminder::query()->firstOrCreate([
            'employee_id' => $employee->id,
            'date' => $today,
            'type' => 'not_checked_in',
        ]);

        if (! $reminder->wasRecentlyCreated) {
            return false;
        }

        $content = (string) __('discord.reminders.not_checked_in', [
            'time' => $start->format('H:i'),
        ]);

        $this->send($employee, $content);

        return true;
    }

    private function remindAway(Employee $employee, AttendanceSession $session, AttendanceSetting $settings, CarbonImmutable $now): bool
    {
        $break = $session->breaks->first(fn (AttendanceBreak $break): bool => $break->ended_at === null);

        if ($break === null || $break->away_reminder_sent_at !== null) {
            return false;
        }

        $limitAt = $break->started_at->addMinutes($settings->away_limit_minutes);

        if ($now->lt($limitAt)) {
            return false;
        }

        $break->update(['away_reminder_sent_at' => $now->utc()]);

        $this->send($employee, (string) __('discord.reminders.away_too_long', [
            'time' => $break->started_at->setTimezone(AlgerianDate::TZ)->format('H:i'),
            'minutes' => $settings->away_limit_minutes,
        ]));

        return true;
    }

    private function remindLongSession(Employee $employee, AttendanceSession $session, AttendanceSetting $settings, CarbonImmutable $now): bool
    {
        if (! $settings->over_10h_reminder_enabled || $session->long_reminder_sent_at !== null) {
            return false;
        }

        if ($session->started_at->gt($now->subHours(10)->utc())) {
            return false;
        }

        $session->update(['long_reminder_sent_at' => $now->utc()]);

        $this->send($employee, (string) __('discord.corrections.long_session', [
            'time' => $session->started_at->setTimezone(AlgerianDate::TZ)->format('H:i'),
        ]));

        return true;
    }

    private function send(Employee $employee, string $content): void
    {
        $discordUserId = (string) $employee->discord_user_id;
        $channelId = $employee->office?->attendance_channel_id;

        SendDiscordDm::dispatch(
            $discordUserId,
            $content,
            [],
            $channelId,
            $channelId === null ? null : '<@'.$discordUserId.'> '.$content,
        );
    }
}
