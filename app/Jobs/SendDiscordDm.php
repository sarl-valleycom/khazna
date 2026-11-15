<?php

namespace App\Jobs;

use App\Discord\DiscordClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SendDiscordDm implements ShouldQueue
{
    use Queueable;

    /**
     * @param  list<array<string, mixed>>  $components
     */
    public function __construct(
        public readonly string $userId,
        public readonly string $content,
        public readonly array $components = [],
        public readonly ?string $fallbackChannelId = null,
        public readonly ?string $fallbackContent = null,
    ) {}

    public function handle(DiscordClient $discord): void
    {
        if ($discord->sendDirectMessage($this->userId, $this->content, $this->components)) {
            return;
        }

        $channelId = (string) ($this->fallbackChannelId ?? '');

        if ($channelId === '') {
            Log::warning('Discord DM failed and there is no attendance channel for the fallback.', [
                'user_id' => $this->userId,
            ]);

            return;
        }

        Log::warning('Discord DM failed; mentioning the employee in the attendance channel.', [
            'user_id' => $this->userId,
            'channel_id' => $channelId,
        ]);

        $discord->sendChannelMessage(
            $channelId,
            $this->fallbackContent ?? '<@'.$this->userId.'> '.$this->content,
            [],
        );
    }
}
