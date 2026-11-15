<?php

namespace App\Console\Commands;

use App\Enums\AttendanceReviewStatus;
use App\Enums\AttendanceStatus;
use App\Enums\EmployeeStatus;
use App\Jobs\SendDiscordDm;
use App\Models\AttendanceSession;
use App\Support\AlgerianDate;
use Illuminate\Console\Command;

class AttendanceCorrectionRemindersCommand extends Command
{
    protected $signature = 'attendance:correction-reminders';

    protected $description = 'DM employees whose sessions were auto-closed and still need a correction';

    public function handle(): int
    {
        $now = AlgerianDate::now()->utc();
        $sent = 0;

        AttendanceSession::query()
            ->where('status', AttendanceStatus::AutoClosed)
            ->where('review_status', AttendanceReviewStatus::NeedsReview)
            ->whereNull('correction_dm_sent_at')
            ->where('ended_at', '<=', $now)
            ->whereDoesntHave('correctionRequests', fn ($query) => $query->pending())
            ->whereHas('employee', fn ($query) => $query
                ->where('status', EmployeeStatus::Active)
                ->whereNotNull('discord_user_id'))
            ->with(['employee.office'])
            ->orderBy('id')
            ->chunkById(100, function ($sessions) use (&$sent): void {
                foreach ($sessions as $session) {
                    $employee = $session->employee;

                    if ($employee === null || $employee->discord_user_id === null) {
                        continue;
                    }

                    $endedAt = $session->ended_at?->setTimezone(AlgerianDate::TZ);

                    if ($endedAt === null) {
                        continue;
                    }

                    $components = [
                        [
                            'type' => 1,
                            'components' => [
                                [
                                    'type' => 2,
                                    'style' => 1,
                                    'label' => (string) __('discord.labels.correct_end_time'),
                                    'custom_id' => 'attendance:correct:'.$session->id,
                                ],
                            ],
                        ],
                    ];

                    $content = (string) __('discord.corrections.dm', [
                        'date' => $endedAt->format('d M Y'),
                        'time' => $endedAt->format('H:i'),
                    ]);
                    $channelId = $employee->office?->attendance_channel_id;

                    SendDiscordDm::dispatch(
                        $employee->discord_user_id,
                        $content,
                        $components,
                        $channelId,
                        $channelId === null ? null : '<@'.$employee->discord_user_id.'> '.$content,
                    );

                    $session->update(['correction_dm_sent_at' => AlgerianDate::now()->utc()]);
                    $sent++;
                }
            });

        $this->info("Sent {$sent} correction request(s).");

        return self::SUCCESS;
    }
}
