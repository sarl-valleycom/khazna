<?php

use App\Discord\CommandRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

test('discord:register-commands pushes the guild command registry with the bot token', function () {
    config()->set([
        'discord.application_id' => '123456789012345678',
        'discord.guild_id' => '987654321098765432',
        'discord.bot_token' => 'test-bot-token',
        'discord.api_base' => 'https://discord.test/api/v10',
    ]);

    Http::fake([
        'discord.test/*' => Http::response([], 200),
    ]);

    $this->artisan('discord:register-commands')->assertSuccessful();

    Http::assertSent(function (Request $request): bool {
        $names = collect($request->data())->pluck('name')->all();

        return $request->method() === 'PUT'
            && $request->url() === 'https://discord.test/api/v10/applications/123456789012345678/guilds/987654321098765432/commands'
            && $request->hasHeader('Authorization', 'Bot test-bot-token')
            && $names === [
                CommandRegistry::CHECK_IN,
                CommandRegistry::CHECK_OUT,
                CommandRegistry::TMP_CHECK_OUT,
                CommandRegistry::STATUS,
                CommandRegistry::MY_HOURS,
            ];
    });
});

test('discord:register-commands fails cleanly when Discord rejects the registry', function () {
    config()->set([
        'discord.application_id' => '1',
        'discord.guild_id' => '2',
        'discord.bot_token' => 'token',
        'discord.api_base' => 'https://discord.test/api/v10',
    ]);

    Http::fake([
        'discord.test/*' => Http::response(['message' => 'Unauthorized'], 401),
    ]);

    $this->artisan('discord:register-commands')->assertFailed();
});
