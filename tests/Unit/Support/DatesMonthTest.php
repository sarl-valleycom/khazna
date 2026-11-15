<?php

use App\Support\Dates;
use Carbon\CarbonImmutable;

afterEach(function () {
    CarbonImmutable::setTestNow();
});

test('a month is read as the first day of that month in Algiers', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-12-05 10:00:00', 'Africa/Algiers'));

    $month = Dates::month('2026-03');

    expect($month->toDateString())->toBe('2026-03-01')
        ->and($month->timezoneName)->toBe('Africa/Algiers');
});

test('anything that is not a real month, or is in the future, becomes the current month', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-12-05 10:00:00', 'Africa/Algiers'));

    foreach ([null, '', 'nonsense', '2026-13', '2026-00', '2026-3', '2026-03-01', ['2026-03'], 202603, '2030-01', '2026-12'] as $value) {
        expect(Dates::month($value)->format('Y-m'))->toBe('2026-12');
    }
});
