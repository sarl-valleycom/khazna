<?php

use App\Discord\DiscordClient;
use App\Jobs\SendDiscordDm;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;

uses(RefreshDatabase::class);

function discordMessagingConfig(): void
{
    config()->set([
        'discord.api_base' => 'https://discord.test/api/v10',
        'discord.bot_token' => 'test-bot-token',
        'discord.timeout' => 5,
    ]);
}

test('a rate-limited channel post honours Retry-After and retries', function () {
    discordMessagingConfig();
    Sleep::fake();

    Http::fake([
        'discord.test/api/v10/channels/777777777777777777/messages' => Http::sequence()
            ->push(['message' => 'rate limited'], 429, ['Retry-After' => '2'])
            ->push([], 200),
    ]);

    $sent = app(DiscordClient::class)->sendChannelMessage('777777777777777777', 'Hello');

    expect($sent)->toBeTrue();
    Sleep::assertSleptTimes(1);
    Http::assertSentCount(2);
});

test('a failed DM falls back to a mention in the attendance channel', function () {
    discordMessagingConfig();
    Log::spy();

    Http::fake([
        'discord.test/api/v10/users/@me/channels' => Http::response(['message' => 'Forbidden'], 403),
        'discord.test/api/v10/channels/777777777777777777/messages' => Http::response([], 200),
    ]);

    (new SendDiscordDm(
        '444444444444444444',
        'You forgot to check out.',
        [],
        '777777777777777777',
        '<@444444444444444444> You forgot to check out.',
    ))->handle(app(DiscordClient::class));

    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
        && $request->url() === 'https://discord.test/api/v10/channels/777777777777777777/messages'
        && $request->data()['content'] === '<@444444444444444444> You forgot to check out.');

    Log::shouldHaveReceived('warning')->withArgs(fn (string $message): bool => str_contains($message, 'mentioning the employee'));
});

test('a failed DM without a fallback channel is only logged', function () {
    discordMessagingConfig();
    Log::spy();

    Http::fake([
        'discord.test/api/v10/users/@me/channels' => Http::response(['message' => 'Forbidden'], 403),
    ]);

    (new SendDiscordDm('444444444444444444', 'Hello'))->handle(app(DiscordClient::class));

    Http::assertSentCount(1);
    Log::shouldHaveReceived('warning')->withArgs(fn (string $message): bool => str_contains($message, 'no attendance channel'));
});
