<?php

namespace App\Attendance;

use Carbon\CarbonImmutable;
use RuntimeException;

/**
 * A state-machine violation, already phrased for the employee. The router
 * translates it into an ephemeral Discord reply.
 */
final class AttendanceException extends RuntimeException
{
    /**
     * @param  array<string, string>  $parameters
     */
    public function __construct(
        public readonly string $translationKey,
        public readonly array $parameters = [],
    ) {
        parent::__construct($translationKey);
    }

    public static function notLinked(): self
    {
        return new self('discord.errors.not_linked');
    }

    public static function inactive(): self
    {
        return new self('discord.errors.inactive');
    }

    public static function noOpenSession(): self
    {
        return new self('discord.errors.no_open_session');
    }

    public static function alreadyCheckedIn(CarbonImmutable $startedAt): self
    {
        return new self('discord.errors.already_checked_in', [
            'time' => $startedAt->setTimezone('Africa/Algiers')->format('H:i'),
        ]);
    }

    public static function alreadyAway(CarbonImmutable $startedAt): self
    {
        return new self('discord.errors.already_away', [
            'time' => $startedAt->setTimezone('Africa/Algiers')->format('H:i'),
        ]);
    }

    public static function notAway(): self
    {
        return new self('discord.errors.not_away');
    }

    public static function reasonRequired(): self
    {
        return new self('discord.errors.reason_required');
    }

    public function messageForEmployee(): string
    {
        return (string) __($this->translationKey, $this->parameters);
    }
}
