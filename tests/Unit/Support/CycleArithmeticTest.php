<?php

use App\Support\CycleArithmetic;

test('monthly cycles clamp to the last day of shorter months', function () {
    expect(CycleArithmetic::addMonths('2026-01-31', 1)->toDateString())->toBe('2026-02-28')
        ->and(CycleArithmetic::addMonths('2026-01-31', 2)->toDateString())->toBe('2026-03-31')
        ->and(CycleArithmetic::addMonths('2028-01-31', 1)->toDateString())->toBe('2028-02-29');
});

test('yearly cycles handle leap days', function () {
    expect(CycleArithmetic::addYears('2028-02-29', 1)->toDateString())->toBe('2029-02-28')
        ->and(CycleArithmetic::addYears('2028-02-29', 4)->toDateString())->toBe('2032-02-29');
});

test('quarterly cycles add three months without overflow', function () {
    expect(CycleArithmetic::addQuarters('2026-11-30', 1)->toDateString())->toBe('2027-02-28');
});
