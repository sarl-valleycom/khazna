<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use InvalidArgumentException;
use Stringable;

/**
 * A reporting period: monthly (2026-12), quarterly (2026-Q4), yearly (2027)
 * or a one-time occurrence anchored on a calendar date.
 */
final class Period implements Stringable
{
    public const MONTHLY = 'monthly';

    public const QUARTERLY = 'quarterly';

    public const YEARLY = 'yearly';

    public const ONCE = 'once';

    public function __construct(
        public readonly string $kind,
        public readonly CarbonImmutable $anchor,
    ) {}

    public static function monthly(CarbonInterface|string $date): self
    {
        return new self(self::MONTHLY, AlgerianDate::toAlgiers($date)->startOfMonth());
    }

    public static function quarterly(CarbonInterface|string $date): self
    {
        return new self(self::QUARTERLY, AlgerianDate::toAlgiers($date)->startOfQuarter());
    }

    public static function yearly(CarbonInterface|string $date): self
    {
        return new self(self::YEARLY, AlgerianDate::toAlgiers($date)->startOfYear());
    }

    public static function once(CarbonInterface|string $date): self
    {
        return new self(self::ONCE, AlgerianDate::toAlgiers($date)->startOfDay());
    }

    public static function parse(string $key, CarbonInterface|string|null $anchor = null): self
    {
        $key = trim($key);

        if ($key === self::ONCE) {
            return self::once($anchor ?? AlgerianDate::today());
        }

        if (preg_match('/^(\d{4})-(\d{2})$/', $key, $matches) === 1) {
            $month = (int) $matches[2];

            if ($month < 1 || $month > 12) {
                throw new InvalidArgumentException("Invalid period key [{$key}].");
            }

            return self::monthly(sprintf('%04d-%02d-01', (int) $matches[1], $month));
        }

        if (preg_match('/^(\d{4})-Q([1-4])$/', $key, $matches) === 1) {
            $month = ((int) $matches[2] - 1) * 3 + 1;

            return self::quarterly(sprintf('%04d-%02d-01', (int) $matches[1], $month));
        }

        if (preg_match('/^\d{4}$/', $key) === 1) {
            return self::yearly($key.'-01-01');
        }

        throw new InvalidArgumentException("Invalid period key [{$key}].");
    }

    public function key(): string
    {
        return match ($this->kind) {
            self::MONTHLY => $this->anchor->format('Y-m'),
            self::QUARTERLY => $this->anchor->format('Y').'-Q'.$this->anchor->quarter,
            self::YEARLY => $this->anchor->format('Y'),
            default => self::ONCE,
        };
    }

    public function start(): CarbonImmutable
    {
        return match ($this->kind) {
            self::MONTHLY => $this->anchor->startOfMonth(),
            self::QUARTERLY => $this->anchor->startOfQuarter(),
            self::YEARLY => $this->anchor->startOfYear(),
            default => $this->anchor->startOfDay(),
        };
    }

    public function end(): CarbonImmutable
    {
        return match ($this->kind) {
            self::MONTHLY => $this->anchor->endOfMonth(),
            self::QUARTERLY => $this->anchor->endOfQuarter(),
            self::YEARLY => $this->anchor->endOfYear(),
            default => $this->anchor->endOfDay(),
        };
    }

    public function next(): ?self
    {
        return match ($this->kind) {
            self::MONTHLY => new self(self::MONTHLY, $this->anchor->addMonthsNoOverflow(1)->startOfMonth()),
            self::QUARTERLY => new self(self::QUARTERLY, $this->anchor->addMonthsNoOverflow(3)->startOfQuarter()),
            self::YEARLY => new self(self::YEARLY, $this->anchor->addMonthsNoOverflow(12)->startOfYear()),
            default => null,
        };
    }

    public function contains(CarbonInterface|string $date): bool
    {
        $value = AlgerianDate::toAlgiers($date);

        return $value->betweenIncluded($this->start(), $this->end());
    }

    public function label(): string
    {
        return match ($this->kind) {
            self::MONTHLY => $this->anchor->format('F Y'),
            self::QUARTERLY => 'Q'.$this->anchor->quarter.' '.$this->anchor->format('Y'),
            self::YEARLY => $this->anchor->format('Y'),
            default => 'One-time',
        };
    }

    public function __toString(): string
    {
        return $this->key();
    }
}
