<?php

use App\Support\AlgerianDate;
use Carbon\Carbon;

afterEach(function () {
    Carbon::setTestNow();
});

test('23:30 UTC is 00:30 on the next day in Algiers', function () {
    $algiers = AlgerianDate::toAlgiers('2026-01-14 23:30:00 UTC');

    expect($algiers->format('Y-m-d H:i'))->toBe('2026-01-15 00:30')
        ->and($algiers->timezoneName)->toBe('Africa/Algiers');
});

test('today comes from the Algiers calendar day', function () {
    Carbon::setTestNow(Carbon::parse('2026-01-14 23:30:00', 'UTC'));

    expect(AlgerianDate::today()->toDateString())->toBe('2026-01-15')
        ->and(AlgerianDate::todayString())->toBe('2026-01-15')
        ->and(AlgerianDate::isToday('2026-01-15'))->toBeTrue()
        ->and(AlgerianDate::isToday('2026-01-14'))->toBeFalse();
});

test('start and end of an Algiers day are converted back to UTC', function () {
    expect(AlgerianDate::startOfDayUtc('2026-01-15')->format('Y-m-d H:i:s'))->toBe('2026-01-14 23:00:00')
        ->and(AlgerianDate::endOfDayUtc('2026-01-15')->format('Y-m-d H:i:s'))->toBe('2026-01-15 22:59:59');
});

test('weeks start on Saturday', function () {
    expect(AlgerianDate::startOfWeek('2026-11-13')->toDateString())->toBe('2026-11-07')
        ->and(AlgerianDate::endOfWeek('2026-11-13')->toDateString())->toBe('2026-11-13')
        ->and(AlgerianDate::startOfWeek('2026-11-14')->toDateString())->toBe('2026-11-14');
});

test('month boundaries are calendar boundaries', function () {
    expect(AlgerianDate::startOfMonth('2026-11-13')->toDateString())->toBe('2026-11-01')
        ->and(AlgerianDate::endOfMonth('2026-11-13')->toDateString())->toBe('2026-11-30')
        ->and(AlgerianDate::endOfMonth('2028-02-10')->toDateString())->toBe('2028-02-29');
});

test('format renders English month names', function () {
    expect(AlgerianDate::format('2026-11-13'))->toBe('13 Nov 2026');
});
