<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/** Strict parsing for dates that arrive in a URL or a form. A value that is not a real date is not a date. */
final class Dates
{
    /**
     * A real calendar day written as YYYY-MM-DD, or null (2026-13-45 and 2026-02-30 are both null).
     * Anything that is not text is null too: a value typed into the address bar can be an array
     * (?from[]=x), and reading that as a string would be a 500.
     */
    public static function day(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return null;
        }

        // createFromFormat quietly rolls 2026-02-30 over to March, so the round trip must match.
        $date = CarbonImmutable::createFromFormat('!Y-m-d', $value);

        return $date?->format('Y-m-d') === $value ? $date : null;
    }

    /**
     * A month written as YYYY-MM, as the first day of that month in Algiers. Anything
     * that is not a real month, and any month after the current one, becomes the
     * current month: a report is never asked for the future.
     */
    public static function month(mixed $value): CarbonImmutable
    {
        $current = AlgerianDate::today()->startOfMonth();

        if (! is_string($value) || preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $value) !== 1) {
            return $current;
        }

        $month = CarbonImmutable::parse($value.'-01', AlgerianDate::TZ)->startOfMonth();

        return $month->gt($current) ? $current : $month;
    }
}
