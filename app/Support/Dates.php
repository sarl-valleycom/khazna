<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/** Strict parsing for dates that arrive in a URL or a form. A value that is not a real date is not a date. */
final class Dates
{
    /** A real calendar day written as YYYY-MM-DD, or null (2026-13-45 and 2026-02-30 are both null). */
    public static function day(?string $value): ?CarbonImmutable
    {
        if ($value === null || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return null;
        }

        // createFromFormat quietly rolls 2026-02-30 over to March, so the round trip must match.
        $date = CarbonImmutable::createFromFormat('!Y-m-d', $value);

        return $date?->format('Y-m-d') === $value ? $date : null;
    }
}
