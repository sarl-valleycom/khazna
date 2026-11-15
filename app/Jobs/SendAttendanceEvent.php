<?php

namespace App\Jobs;

use App\Discord\DiscordClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Channel posts never happen inside an interaction handler: Discord expects an
 * answer within three seconds and an outage must not fail the attendance write.
 */
class SendAttendanceEvent implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $channelId,
        public readonly string $content,
    ) {}

    public function handle(DiscordClient $discord): void
    {
        if ($this->channelId === '') {
            Log::warning('Attendance event dropped: no channel ID.');

            return;
        }

        $discord->sendChannelMessage($this->channelId, $this->content);
    }
}
