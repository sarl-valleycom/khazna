<?php

namespace App\Console\Commands;

use App\Attendance\WeeklyDigest;
use App\Discord\DiscordClient;
use App\Models\AttendanceSetting;
use App\Support\AlgerianDate;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class AttendanceDigestWeeklyCommand extends Command
{
    protected $signature = 'attendance:digest-weekly';

    protected $description = 'Post the weekly attendance summary to the owner channel';

    public function handle(WeeklyDigest $digest, DiscordClient $discord): int
    {
        $channelId = (string) (AttendanceSetting::current()->owner_digest_channel_id ?? '');

        if ($channelId === '') {
            Log::warning('Weekly attendance digest skipped: no owner digest channel is configured.');
            $this->warn('No owner digest channel is configured.');

            return self::SUCCESS;
        }

        $sent = $discord->sendChannelMessage($channelId, '', [$digest->embed(AlgerianDate::now())]);

        $this->info($sent ? 'Weekly attendance digest posted.' : 'Weekly attendance digest failed.');

        return $sent ? self::SUCCESS : self::FAILURE;
    }
}
