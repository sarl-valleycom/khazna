<?php

namespace App\Discord;

use GuzzleHttp\Psr7\Response as PsrResponse;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;

/**
 * The only place that talks to the Discord REST API. Channel IDs and user IDs
 * are strings end to end: Discord IDs are 64-bit integers and a JavaScript
 * number would silently change a value above 2^53.
 */
class DiscordClient
{
    /**
     * Create or replace the application's global commands. Global commands are
     * the only ones Discord shows in a DM with the bot, which is how employees
     * send /check-in. A change can take up to an hour to appear everywhere.
     *
     * @param  list<array<string, mixed>>  $commands
     */
    public function registerGlobalCommands(array $commands): bool
    {
        $applicationId = (string) config('discord.application_id');

        if ($applicationId === '') {
            Log::error('Discord command registration skipped: application ID is missing.');

            return false;
        }

        return $this->putCommands("/applications/{$applicationId}/commands", $commands);
    }

    /**
     * Remove the configured guild's own commands. Without this a command that
     * is both global and guild-specific shows up twice in the server.
     */
    public function clearGuildCommands(): bool
    {
        $applicationId = (string) config('discord.application_id');
        $guildId = (string) config('discord.guild_id');

        if ($applicationId === '' || $guildId === '') {
            return false;
        }

        return $this->putCommands("/applications/{$applicationId}/guilds/{$guildId}/commands", []);
    }

    /**
     * @param  list<array<string, mixed>>  $commands
     */
    private function putCommands(string $path, array $commands): bool
    {
        $response = $this->sendWithRetry('PUT', $path, $commands);

        if (! $response->successful()) {
            Log::error('Discord command registration failed.', [
                'path' => $path,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return false;
        }

        return true;
    }

    /**
     * Open (or reuse) a DM channel and post a message to it. Discord requires
     * two calls for this. Failures are logged and never thrown into a job that
     * is also posting to a channel.
     *
     * @param  list<array<string, mixed>>  $components
     */
    public function sendDirectMessage(string $userId, string $content, array $components = []): bool
    {
        $channel = $this->sendWithRetry('POST', '/users/@me/channels', ['recipient_id' => $userId]);

        if (! $channel->successful()) {
            Log::error('Discord DM channel could not be opened.', [
                'user_id' => $userId,
                'status' => $channel->status(),
                'body' => $channel->body(),
            ]);

            return false;
        }

        $channelId = $channel->json('id');

        if (! is_string($channelId) && ! is_int($channelId)) {
            Log::error('Discord DM channel response had no usable id.', ['user_id' => $userId]);

            return false;
        }

        $payload = ['content' => $content];

        if ($components !== []) {
            $payload['components'] = $components;
        }

        $message = $this->sendWithRetry('POST', "/channels/{$channelId}/messages", $payload);

        if (! $message->successful()) {
            Log::error('Discord DM failed.', [
                'user_id' => $userId,
                'status' => $message->status(),
                'body' => $message->body(),
            ]);

            return false;
        }

        return true;
    }

    /**
     * Post a plain message to a channel.
     *
     * @param  list<array<string, mixed>>  $embeds
     */
    public function sendChannelMessage(string $channelId, string $content, array $embeds = []): bool
    {
        $payload = $content === '' ? [] : ['content' => $content];

        if ($embeds !== []) {
            $payload['embeds'] = $embeds;
        }

        $response = $this->sendWithRetry('POST', "/channels/{$channelId}/messages", $payload);

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

    /**
     * Discord answers 429 with a Retry-After header and can briefly return a
     * 5xx. A timeout or dropped connection is treated like a 5xx. Back off,
     * then give up cleanly (a synthetic 503 when nothing came back) so the
     * caller can log the failure and use its fallback.
     *
     * @param  array<int|string, mixed>  $payload
     */
    private function sendWithRetry(string $method, string $path, array $payload): Response
    {
        $attempts = 3;

        for ($attempt = 1; ; $attempt++) {
            try {
                $response = $this->request()->send($method, $path, ['json' => $payload]);
            } catch (ConnectionException $exception) {
                $delay = 2 ** ($attempt - 1);

                Log::warning('Discord request could not reach Discord.', [
                    'method' => $method,
                    'path' => $path,
                    'attempt' => $attempt,
                    'delay_seconds' => $delay,
                    'error' => $exception->getMessage(),
                ]);

                if ($attempt >= $attempts) {
                    return new Response(new PsrResponse(503));
                }

                Sleep::for($delay)->seconds();

                continue;
            }

            if (! in_array($response->status(), [429, 500, 502, 503, 504], true) || $attempt >= $attempts) {
                return $response;
            }

            $delay = $response->status() === 429
                ? $this->retryAfter($response)
                : 2 ** ($attempt - 1);

            Log::warning('Discord request failed; retrying with backoff.', [
                'method' => $method,
                'path' => $path,
                'status' => $response->status(),
                'attempt' => $attempt,
                'delay_seconds' => $delay,
            ]);

            Sleep::for($delay)->seconds();
        }
    }

    private function retryAfter(Response $response): int
    {
        $header = $response->header('Retry-After');
        $seconds = is_numeric($header) ? (float) $header : 1.0;

        return (int) max(1, min(30, ceil($seconds)));
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
