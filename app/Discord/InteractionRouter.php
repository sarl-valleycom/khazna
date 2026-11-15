<?php

namespace App\Discord;

use App\Attendance\AttendanceException;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Maps an interaction type and command name (or a button/modal custom_id
 * prefix) to a handler. Handlers only touch the database: Discord expects an
 * answer within three seconds, so channel posts and DMs are queued jobs.
 */
class InteractionRouter
{
    /** @var array<string, class-string<InteractionHandler>> */
    private array $commands = [];

    /** @var array<string, class-string<InteractionHandler>> */
    private array $componentPrefixes = [];

    /** @var array<string, class-string<InteractionHandler>> */
    private array $modalPrefixes = [];

    /**
     * @param  array<string, class-string<InteractionHandler>>  $commands
     * @param  array<string, class-string<InteractionHandler>>  $componentPrefixes
     * @param  array<string, class-string<InteractionHandler>>  $modalPrefixes
     */
    public function __construct(
        array $commands = [],
        array $componentPrefixes = [],
        array $modalPrefixes = [],
    ) {
        $this->commands = $commands;
        $this->componentPrefixes = $componentPrefixes;
        $this->modalPrefixes = $modalPrefixes;
    }

    public function handle(Interaction $interaction): InteractionResponse
    {
        $handlerClass = $this->handlerFor($interaction);

        if ($handlerClass === null) {
            return InteractionResponse::message(
                (string) __('discord.errors.unknown_interaction'),
                ephemeral: true,
            );
        }

        try {
            return app($handlerClass)->handle($interaction);
        } catch (AttendanceException $exception) {
            return InteractionResponse::message(
                $exception->messageForEmployee(),
                ephemeral: true,
            );
        } catch (Throwable $exception) {
            Log::error('Discord interaction failed.', [
                'command' => $interaction->commandName(),
                'custom_id' => $interaction->customId(),
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return InteractionResponse::message(
                (string) __('discord.errors.generic'),
                ephemeral: true,
            );
        }
    }

    /**
     * @return class-string<InteractionHandler>|null
     */
    private function handlerFor(Interaction $interaction): ?string
    {
        return match ($interaction->type()) {
            Interaction::TYPE_COMMAND => $this->commands[$interaction->commandName() ?? ''] ?? null,
            Interaction::TYPE_COMPONENT => $this->byPrefix($this->componentPrefixes, (string) $interaction->customId()),
            Interaction::TYPE_MODAL_SUBMIT => $this->byPrefix($this->modalPrefixes, (string) $interaction->customId()),
            default => null,
        };
    }

    /**
     * @param  array<string, class-string<InteractionHandler>>  $map
     * @return class-string<InteractionHandler>|null
     */
    private function byPrefix(array $map, string $customId): ?string
    {
        foreach ($map as $prefix => $handler) {
            if (str_starts_with($customId, $prefix)) {
                return $handler;
            }
        }

        return null;
    }
}
