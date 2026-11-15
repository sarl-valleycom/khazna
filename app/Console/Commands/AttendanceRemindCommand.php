<?php

namespace App\Console\Commands;

use App\Enums\EmployeeStatus;
use App\Jobs\SendDiscordDm;
use App\Models\AttendanceSession;
use App\Models\AttendanceSetting;
use App\Support\AlgerianDate;
use Illuminate\Console\Command;

class AttendanceRemindCommand extends Command
{
    protected $signature = 'attendance:remind';

    protected $description = 'DM employees who have been working for more than ten hours';

    public function handle(): int
    {
        $settings = AttendanceSetting::current();

        if (! $settings->reminders_enabled || ! $settings->over_10h_reminder_enabled) {
            $this->info('Reminders are disabled.');

            return self::SUCCESS;
        }

        $now = AlgerianDate::now();
        $sent = 0;

        AttendanceSession::query()
            ->open()
            ->whereNull('long_reminder_sent_at')
            ->where('started_at', '<=', $now->subHours(10)->utc())
            ->whereHas('employee', fn ($query) => $query
                ->where('status', EmployeeStatus::Active)
                ->where('reminders_enabled', true)
                ->whereNotNull('discord_user_id'))
            ->with('employee')
            ->orderBy('id')
            ->chunkById(100, function ($sessions) use ($now, &$sent): void {
                foreach ($sessions as $session) {
                    $employee = $session->employee;

                    if ($employee === null || $employee->discord_user_id === null) {
                        continue;
                    }

                    SendDiscordDm::dispatch(
                        $employee->discord_user_id,
                        (string) __('discord.corrections.long_session', [
                            'time' => $session->started_at->setTimezone(AlgerianDate::TZ)->format('H:i'),
                        ]),
                    );

                    $session->update(['long_reminder_sent_at' => $now->utc()]);
                    $sent++;
                }
            });

        $this->info("Sent {$sent} long-session reminder(s).");

        return self::SUCCESS;
    }
}
