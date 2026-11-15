<?php

namespace App\Attendance;

use Carbon\CarbonImmutable;
use RuntimeException;

/**
 * A state-machine violation, already phrased for the employee. The router
 * shows it to the employee.
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
        return new self('attendance.errors.not_linked');
    }

    public static function inactive(): self
    {
        return new self('attendance.errors.inactive');
    }

    public static function noOpenSession(): self
    {
        return new self('attendance.errors.no_open_session');
    }

    public static function alreadyCheckedIn(CarbonImmutable $startedAt): self
    {
        return new self('attendance.errors.already_checked_in', [
            'time' => $startedAt->setTimezone('Africa/Algiers')->format('H:i'),
        ]);
    }

    public static function alreadyAway(CarbonImmutable $startedAt): self
    {
        return new self('attendance.errors.already_away', [
            'time' => $startedAt->setTimezone('Africa/Algiers')->format('H:i'),
        ]);
    }

    public static function notAway(): self
    {
        return new self('attendance.errors.not_away');
    }

    public static function reasonRequired(): self
    {
        return new self('attendance.errors.reason_required');
    }

    public static function sessionNotFound(): self
    {
        return new self('attendance.errors.session_not_found');
    }

    public static function notClosed(): self
    {
        return new self('attendance.errors.not_closed');
    }

    public static function notCheckedOut(): self
    {
        return new self('attendance.errors.not_checked_out');
    }

    public static function reportRequired(): self
    {
        return new self('attendance.errors.report_required');
    }

    public static function invalidEndTime(): self
    {
        return new self('attendance.errors.invalid_end_time');
    }

    public static function invalidTimeFormat(): self
    {
        return new self('attendance.errors.invalid_time_format');
    }

    public function messageForEmployee(): string
    {
        return (string) __($this->translationKey, $this->parameters);
    }
}
