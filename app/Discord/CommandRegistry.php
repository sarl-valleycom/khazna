<?php

namespace App\Discord;

/**
 * The single definition of the slash commands pushed to Discord. The artisan
 * command discord:register-commands reads this class; handlers use the same
 * names, so a command cannot be registered without a handler or the reverse.
 */
final class CommandRegistry
{
    /** Where a command can be used: in a server, and in a DM with the bot. */
    private const CONTEXTS = [0, 1];

    /** Installed to a server (the bot), not to a user account. */
    private const INTEGRATION_TYPES = [0];

    public const CHECK_IN = 'check-in';

    public const CHECK_OUT = 'check-out';

    public const TMP_CHECK_OUT = 'tmp-check-out';

    public const STATUS = 'status';

    public const MY_HOURS = 'my-hours';

    /**
     * Every command works in a server and in a DM with the bot, so an employee
     * can send /check-in straight to the bot. Discord only shows a command in a
     * DM when it is registered globally.
     *
     * @return list<array<string, mixed>>
     */
    public function commands(): array
    {
        return array_map(fn (array $command): array => [
            ...$command,
            'contexts' => self::CONTEXTS,
            'integration_types' => self::INTEGRATION_TYPES,
        ], $this->definitions());
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function definitions(): array
    {
        return [
            [
                'name' => self::CHECK_IN,
                'description' => (string) __('discord.commands.check_in'),
                'type' => 1,
            ],
            [
                'name' => self::CHECK_OUT,
                'description' => (string) __('discord.commands.check_out'),
                'type' => 1,
            ],
            [
                'name' => self::TMP_CHECK_OUT,
                'description' => (string) __('discord.commands.tmp_check_out'),
                'type' => 1,
                'options' => [
                    [
                        'type' => 3,
                        'name' => 'reason',
                        'description' => (string) __('discord.commands.tmp_check_out_reason'),
                        'required' => true,
                        'max_length' => 500,
                    ],
                ],
            ],
            [
                'name' => self::STATUS,
                'description' => (string) __('discord.commands.status'),
                'type' => 1,
            ],
            [
                'name' => self::MY_HOURS,
                'description' => (string) __('discord.commands.my_hours'),
                'type' => 1,
                'options' => [
                    [
                        'type' => 3,
                        'name' => 'period',
                        'description' => (string) __('discord.commands.my_hours_period'),
                        'required' => false,
                        'choices' => [
                            ['name' => 'Today', 'value' => 'today'],
                            ['name' => 'Week', 'value' => 'week'],
                            ['name' => 'Month', 'value' => 'month'],
                        ],
                    ],
                ],
            ],
        ];
    }
}
