<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Algiers calendar helpers. Timestamps are stored in UTC; calendar dates are
 * Algiers dates with no time and no timezone shift.
 */
final class AlgerianDate
{
    public const TZ = 'Africa/Algiers';

    public static function now(): CarbonImmutable
    {
        return CarbonImmutable::now(self::TZ);
    }

    public static function today(): CarbonImmutable
    {
        return self::now()->startOfDay();
    }

    public static function todayString(): string
    {
        return self::today()->toDateString();
    }

    public static function toAlgiers(CarbonInterface|string $value): CarbonImmutable
    {
        if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1) {
            return CarbonImmutable::parse($value, self::TZ);
        }

        return CarbonImmutable::parse($value, 'UTC')->tz(self::TZ);
    }

    public static function startOfDayUtc(CarbonInterface|string $date): CarbonImmutable
    {
        return self::toAlgiers($date)->startOfDay()->utc();
    }

    public static function endOfDayUtc(CarbonInterface|string $date): CarbonImmutable
    {
        return self::toAlgiers($date)->endOfDay()->utc();
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public static function dayRangeUtc(CarbonInterface|string $date): array
    {
        return [self::startOfDayUtc($date), self::endOfDayUtc($date)];
    }

    public static function startOfWeek(CarbonInterface|string|null $date = null, ?string $weekStartsOn = null): CarbonImmutable
    {
        return self::toAlgiers($date ?? self::today())
            ->startOfWeek(self::weekDay($weekStartsOn));
    }

    public static function endOfWeek(CarbonInterface|string|null $date = null, ?string $weekStartsOn = null): CarbonImmutable
    {
        return self::startOfWeek($date, $weekStartsOn)->addDays(6)->endOfDay();
    }

    public static function weekDay(?string $weekStartsOn = null): int
    {
        return match (strtolower((string) ($weekStartsOn ?? 'saturday'))) {
            'sunday' => CarbonInterface::SUNDAY,
            'monday' => CarbonInterface::MONDAY,
            'tuesday' => CarbonInterface::TUESDAY,
            'wednesday' => CarbonInterface::WEDNESDAY,
            'thursday' => CarbonInterface::THURSDAY,
            'friday' => CarbonInterface::FRIDAY,
            default => CarbonInterface::SATURDAY,
        };
    }

    public static function startOfMonth(CarbonInterface|string|null $date = null): CarbonImmutable
    {
        return self::toAlgiers($date ?? self::today())->startOfMonth();
    }

    public static function endOfMonth(CarbonInterface|string|null $date = null): CarbonImmutable
    {
        return self::toAlgiers($date ?? self::today())->endOfMonth();
    }

    public static function isToday(CarbonInterface|string $date): bool
    {
        return self::toAlgiers($date)->toDateString() === self::todayString();
    }

    public static function format(CarbonInterface|string $date, string $format = 'd M Y'): string
    {
        return self::toAlgiers($date)->format($format);
    }
}
