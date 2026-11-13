<?php

use App\Support\Period;

test('monthly period key has calendar boundaries and a next period', function () {
    $period = Period::parse('2026-12');

    expect($period->start()->toDateString())->toBe('2026-12-01')
        ->and($period->end()->toDateString())->toBe('2026-12-31')
        ->and($period->next()?->key())->toBe('2027-01')
        ->and($period->label())->toBe('December 2026');
});

test('quarterly period key has calendar boundaries and a next period', function () {
    $period = Period::parse('2026-Q4');

    expect($period->start()->toDateString())->toBe('2026-10-01')
        ->and($period->end()->toDateString())->toBe('2026-12-31')
        ->and($period->next()?->key())->toBe('2027-Q1')
        ->and($period->label())->toBe('Q4 2026');
});

test('yearly period key has calendar boundaries and a next period', function () {
    $period = Period::parse('2027');

    expect($period->start()->toDateString())->toBe('2027-01-01')
        ->and($period->end()->toDateString())->toBe('2027-12-31')
        ->and($period->next()?->key())->toBe('2028')
        ->and($period->label())->toBe('2027');
});

test('once period keeps its anchor date and has no next period', function () {
    $period = Period::parse('once', '2026-11-13');

    expect($period->key())->toBe('once')
        ->and($period->start()->toDateString())->toBe('2026-11-13')
        ->and($period->end()->toDateString())->toBe('2026-11-13')
        ->and($period->next())->toBeNull()
        ->and($period->label())->toBe('One-time');
});

test('an invalid period key is rejected', function () {
    Period::parse('2026-13');
})->throws(InvalidArgumentException::class);
