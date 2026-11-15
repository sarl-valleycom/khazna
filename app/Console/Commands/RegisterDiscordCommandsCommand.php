<?php

namespace App\Console\Commands;

use App\Discord\CommandRegistry;
use App\Discord\DiscordClient;
use Illuminate\Console\Command;

class RegisterDiscordCommandsCommand extends Command
{
    protected $signature = 'discord:register-commands';

    protected $description = 'Push the slash commands in CommandRegistry to the configured Discord guild';

    public function handle(CommandRegistry $registry, DiscordClient $client): int
    {
        if (! $client->registerGuildCommands($registry->commands())) {
            $this->error(__('Discord command registration failed. Check the logs and the Discord settings.'));

            return self::FAILURE;
        }

        $this->info(__('Registered :count Discord commands in guild :guild.', [
            'count' => count($registry->commands()),
            'guild' => (string) config('discord.guild_id'),
        ]));

        return self::SUCCESS;
    }
}
