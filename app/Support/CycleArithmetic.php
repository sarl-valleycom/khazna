<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Calendar arithmetic for contract cycles. Adding one month to January 31
 * gives February 28 (or 29 in a leap year), never March 3.
 */
final class CycleArithmetic
{
    public static function addMonths(CarbonInterface|string $anchor, int $months): CarbonImmutable
    {
        return AlgerianDate::toAlgiers($anchor)->addMonthsNoOverflow($months);
    }

    public static function addQuarters(CarbonInterface|string $anchor, int $quarters): CarbonImmutable
    {
        return self::addMonths($anchor, $quarters * 3);
    }

    public static function addYears(CarbonInterface|string $anchor, int $years): CarbonImmutable
    {
        return self::addMonths($anchor, $years * 12);
    }
}
