<?php

namespace App\Discord;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * The only place that talks to the Discord REST API. Channel IDs and user IDs
 * are strings end to end: Discord IDs are 64-bit integers and a JavaScript
 * number would silently change a value above 2^53.
 */
class DiscordClient
{
    /**
     * Create or replace the guild's commands. Guild commands update instantly;
     * global commands can take up to an hour, which is why this only uses the
     * configured guild (FR-ID-04).
     *
     * @param  list<array<string, mixed>>  $commands
     */
    public function registerGuildCommands(array $commands): bool
    {
        $applicationId = (string) config('discord.application_id');
        $guildId = (string) config('discord.guild_id');

        if ($applicationId === '' || $guildId === '') {
            Log::error('Discord command registration skipped: application ID or guild ID is missing.');

            return false;
        }

        $response = $this->request()->put(
            "/applications/{$applicationId}/guilds/{$guildId}/commands",
            $commands,
        );

        if (! $response->successful()) {
            Log::error('Discord command registration failed.', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return false;
        }

        return true;
    }

    /**
     * Post a plain message to a channel. Rate-limit handling is added in the
     * reminders/digests section; this method only owns the HTTP call.
     *
     * @param  list<array<string, mixed>>  $embeds
     */
    public function sendChannelMessage(string $channelId, string $content, array $embeds = []): bool
    {
        $payload = ['content' => $content];

        if ($embeds !== []) {
            $payload['embeds'] = $embeds;
        }

        $response = $this->request()->post("/channels/{$channelId}/messages", $payload);

        if (! $response->successful()) {
            Log::error('Discord channel message failed.', [
                'channel_id' => $channelId,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return false;
        }

        return true;
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl((string) config('discord.api_base'))
            ->withHeaders(['Authorization' => 'Bot '.((string) config('discord.bot_token'))])
            ->acceptJson()
            ->asJson()
            ->timeout((int) config('discord.timeout'));
    }
}
