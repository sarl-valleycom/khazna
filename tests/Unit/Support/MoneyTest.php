<?php

use App\Support\Money;

test('allocate splits 100,000.00 DA into 40/30/30 exactly', function () {
    $parts = Money::fromCents(10_000_000)->allocate([40, 30, 30]);

    expect($parts[0]->cents)->toBe(4_000_000)
        ->and($parts[1]->cents)->toBe(3_000_000)
        ->and($parts[2]->cents)->toBe(3_000_000);
});

test('allocate keeps the centime total exact when it cannot divide evenly', function () {
    $parts = Money::fromCents(10_000_001)->allocate([40, 30, 30]);

    expect(array_sum(array_map(fn (Money $part) => $part->cents, $parts)))->toBe(10_000_001)
        ->and($parts[0]->cents)->toBe(4_000_000)
        ->and($parts[1]->cents)->toBe(3_000_000)
        ->and($parts[2]->cents)->toBe(3_000_001);
});

test('parse accepts English, plain and French money input', function () {
    expect(Money::parse('10,000.50')?->cents)->toBe(1_000_050)
        ->and(Money::parse('10000.50')?->cents)->toBe(1_000_050)
        ->and(Money::parse('10 000,50')?->cents)->toBe(1_000_050)
        ->and(Money::parse('10 000.50')?->cents)->toBe(1_000_050)
        ->and(Money::parse('10 000,50 DA')?->cents)->toBe(1_000_050);
});

test('parse rejects invalid input', function () {
    expect(Money::parse(''))->toBeNull()
        ->and(Money::parse('abc'))->toBeNull()
        ->and(Money::parse('10,00,0.5x'))->toBeNull();
});

test('parse reads well-formed thousands grouping as whole amounts', function () {
    expect(Money::parse('1,000')?->cents)->toBe(100_000)
        ->and(Money::parse('12.345')?->cents)->toBe(1_234_500)
        ->and(Money::parse('12.345.678')?->cents)->toBe(1_234_567_800)
        ->and(Money::parse('1.234.567,89')?->cents)->toBe(123_456_789)
        ->and(Money::parse('0,50')?->cents)->toBe(50)
        ->and(Money::parse('.5')?->cents)->toBe(50);
});

test('parse rejects ambiguous or malformed grouping instead of guessing', function (string $input) {
    expect(Money::parse($input))->toBeNull();
})->with(['0.125', '0,125', '1.2345', '1,234.567', '1.000.50', '12abc3', '1,00,000', ',', '-']);

test('parse rejects amounts too large to hold exactly instead of overflowing', function () {
    expect(Money::parse('99999999999999999999'))->toBeNull()
        ->and(Money::parse('10000000000000'))->toBeNull()
        ->and(Money::parse('9999999999999')?->cents)->toBe(999_999_999_999_900)
        ->and(Money::parse('-0')?->cents)->toBe(0)
        ->and(Money::parse("1\t000"))->toBeNull();
});

test('format uses English grouping and two decimal places', function () {
    expect(Money::fromCents(1_000_050)->format())->toBe('10,000.50 DA')
        ->and(Money::fromCents(-1_000_050)->format())->toBe('-10,000.50 DA')
        ->and(Money::fromCents(0)->format())->toBe('0.00 DA');
});

test('add, subtract and compare are exact', function () {
    $a = Money::fromCents(1_000_000);
    $b = Money::fromCents(250_000);

    expect($a->add($b)->cents)->toBe(1_250_000)
        ->and($a->subtract($b)->cents)->toBe(750_000)
        ->and($a->compare($b))->toBe(1)
        ->and($b->lessThan($a))->toBeTrue()
        ->and($a->equals(Money::fromCents(1_000_000)))->toBeTrue();
});

test('multiply by a ratio rounds half away from zero', function () {
    expect(Money::fromCents(100)->multiplyByRatio(1, 3)->cents)->toBe(33)
        ->and(Money::fromCents(101)->multiplyByRatio(1, 2)->cents)->toBe(51)
        ->and(Money::fromCents(-101)->multiplyByRatio(1, 2)->cents)->toBe(-51);
});
