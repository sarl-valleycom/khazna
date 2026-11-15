<?php

namespace App\Console\Commands;

use App\Discord\CommandRegistry;
use App\Discord\DiscordClient;
use Illuminate\Console\Command;

class RegisterDiscordCommandsCommand extends Command
{
    protected $signature = 'discord:register-commands';

    protected $description = 'Push the slash commands in CommandRegistry to Discord as global commands (they work in a DM with the bot and in the server)';

    public function handle(CommandRegistry $registry, DiscordClient $client): int
    {
        if (! $client->registerGlobalCommands($registry->commands())) {
            $this->error(__('Discord command registration failed. Check the logs and the Discord settings.'));

            return self::FAILURE;
        }

        $this->info(__('Registered :count global Discord commands. They can take up to an hour to appear.', [
            'count' => count($registry->commands()),
        ]));

        if (! $client->clearGuildCommands()) {
            $this->warn(__('Could not clear the old guild commands; commands may appear twice in the server until they are removed.'));
        }

        return self::SUCCESS;
    }
}
