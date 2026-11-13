<?php

namespace App\Support;

use InvalidArgumentException;
use JsonSerializable;
use Stringable;

/**
 * Immutable value object over integer centimes. Never use floats for money.
 */
final class Money implements JsonSerializable, Stringable
{
    public function __construct(
        public readonly int $cents,
        public readonly string $currency = 'DZD',
        public readonly string $symbol = 'DA',
    ) {}

    public static function fromCents(int $cents, string $currency = 'DZD', string $symbol = 'DA'): self
    {
        return new self($cents, $currency, $symbol);
    }

    public static function zero(string $currency = 'DZD', string $symbol = 'DA'): self
    {
        return new self(0, $currency, $symbol);
    }

    /**
     * Parse user input into integer centimes. Accepts English, plain and French
     * grouping, optional "DA", spaces, commas, dots and apostrophes.
     */
    public static function parse(string|int|float|null $input, string $currency = 'DZD', string $symbol = 'DA'): ?self
    {
        if ($input === null) {
            return null;
        }

        if (is_int($input)) {
            return new self($input, $currency, $symbol);
        }

        if (is_float($input)) {
            if (! is_finite($input)) {
                return null;
            }

            return new self((int) round($input * 100), $currency, $symbol);
        }

        $value = trim($input);
        $value = preg_replace('/\s*DA\s*$/i', '', $value) ?? $value;
        $value = str_replace(["\u{00A0}", "\u{202F}", "\u{2009}", ' ', "'", '_'], '', $value);

        if ($value === '') {
            return null;
        }

        $negative = str_starts_with($value, '-');
        $value = ltrim($value, '+-');

        if ($value === '' || preg_match('/[^0-9.,]/', $value) === 1) {
            return null;
        }

        [$integerPart, $fractionPart] = self::splitDecimal($value);

        if ($integerPart === '' && $fractionPart === '') {
            return null;
        }

        $integerPart = preg_replace('/\D/', '', $integerPart) ?? '';
        $fractionPart = preg_replace('/\D/', '', $fractionPart) ?? '';

        if (strlen($fractionPart) > 2) {
            return null;
        }

        $cents = (int) ($integerPart === '' ? 0 : $integerPart) * 100
            + (int) str_pad($fractionPart, 2, '0');

        return new self($negative ? -$cents : $cents, $currency, $symbol);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private static function splitDecimal(string $value): array
    {
        $lastComma = strrpos($value, ',');
        $lastDot = strrpos($value, '.');
        $lastSeparator = max($lastComma === false ? -1 : $lastComma, $lastDot === false ? -1 : $lastDot);

        if ($lastSeparator === -1) {
            return [$value, ''];
        }

        $after = substr($value, $lastSeparator + 1);
        $digitsAfter = preg_replace('/\D/', '', $after) ?? '';

        $isDecimal = strlen($digitsAfter) > 0 && strlen($digitsAfter) <= 2;

        if (! $isDecimal) {
            return [$value, ''];
        }

        return [substr($value, 0, $lastSeparator), $after];
    }

    public function add(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->cents + $other->cents, $this->currency, $this->symbol);
    }

    public function subtract(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->cents - $other->cents, $this->currency, $this->symbol);
    }

    public function multiplyByRatio(int $numerator, int $denominator): self
    {
        if ($denominator === 0) {
            throw new InvalidArgumentException('Cannot divide by zero.');
        }

        $product = $this->cents * $numerator;
        $sign = $product < 0 ? -1 : 1;
        $absolute = abs($product);
        $quotient = intdiv($absolute, abs($denominator));
        $remainder = $absolute % abs($denominator);

        if ($remainder * 2 >= abs($denominator)) {
            $quotient++;
        }

        if ($denominator < 0) {
            $sign *= -1;
        }

        return new self($sign * $quotient, $this->currency, $this->symbol);
    }

    public function compare(self $other): int
    {
        $this->assertSameCurrency($other);

        return $this->cents <=> $other->cents;
    }

    public function equals(self $other): bool
    {
        return $this->currency === $other->currency && $this->cents === $other->cents;
    }

    public function greaterThan(self $other): bool
    {
        return $this->compare($other) > 0;
    }

    public function lessThan(self $other): bool
    {
        return $this->compare($other) < 0;
    }

    public function isZero(): bool
    {
        return $this->cents === 0;
    }

    public function isNegative(): bool
    {
        return $this->cents < 0;
    }

    public function isPositive(): bool
    {
        return $this->cents > 0;
    }

    /**
     * Split the amount into exact parts. The total is always preserved; any
     * remaining centimes go to the later parts, one each at a time.
     *
     * @param  array<int|float|string>  $ratios
     * @return list<self>
     */
    public function allocate(array $ratios): array
    {
        if ($ratios === []) {
            throw new InvalidArgumentException('At least one ratio is required.');
        }

        $normalized = [];
        $totalRatio = 0;

        foreach ($ratios as $ratio) {
            if (! is_numeric($ratio)) {
                throw new InvalidArgumentException('Ratios must be numbers.');
            }

            $scaled = (int) round((float) $ratio * 1000);

            if ($scaled <= 0) {
                throw new InvalidArgumentException('Ratios must be greater than zero.');
            }

            $normalized[] = $scaled;
            $totalRatio += $scaled;
        }

        $remaining = $this->cents;
        $remainingRatio = $totalRatio;
        $parts = [];
        $lastIndex = count($normalized) - 1;

        foreach ($normalized as $index => $ratio) {
            $share = $index === $lastIndex
                ? $remaining
                : intdiv($remaining * $ratio, $remainingRatio);

            $parts[] = new self($share, $this->currency, $this->symbol);
            $remaining -= $share;
            $remainingRatio -= $ratio;
        }

        return $parts;
    }

    public function format(): string
    {
        return $this->formatWithoutCurrency().' '.$this->symbol;
    }

    public function formatWithoutCurrency(): string
    {
        $absolute = abs($this->cents);
        $integer = intdiv($absolute, 100);
        $fraction = $absolute % 100;

        $formattedInteger = number_format($integer, 0, '.', ',');

        return ($this->cents < 0 ? '-' : '').$formattedInteger.'.'.str_pad((string) $fraction, 2, '0', STR_PAD_LEFT);
    }

    public function toDecimalString(): string
    {
        return $this->formatWithoutCurrency();
    }

    /**
     * @return array{cents: int, currency: string, formatted: string}
     */
    public function jsonSerialize(): array
    {
        return [
            'cents' => $this->cents,
            'currency' => $this->currency,
            'formatted' => $this->format(),
        ];
    }

    public function __toString(): string
    {
        return $this->format();
    }

    private function assertSameCurrency(self $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException('Cannot operate on different currencies.');
        }
    }
}
