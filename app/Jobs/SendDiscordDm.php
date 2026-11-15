<?php

namespace App\Jobs;

use App\Discord\DiscordClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

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
    ) {}

    public function handle(DiscordClient $discord): void
    {
        $discord->sendDirectMessage($this->userId, $this->content, $this->components);
    }
}
