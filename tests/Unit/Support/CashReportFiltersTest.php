<?php

use App\Models\User;
use App\Support\CashReportFilters;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

afterEach(function () {
    CarbonImmutable::setTestNow();
});

function cashFiltersFor(array $query, ?int $officeId = null): CashReportFilters
{
    $user = new User;
    $user->office_id = $officeId;

    return CashReportFilters::fromRequest(Request::create('/', 'GET', $query), $user);
}

test('the period defaults to this year until today', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-03-20 10:00:00', 'Africa/Algiers'));

    $filters = cashFiltersFor([]);

    expect($filters->from->toDateString())->toBe('2026-01-01')
        ->and($filters->to->toDateString())->toBe('2026-03-20')
        ->and($filters->officeId)->toBeNull()
        ->and($filters->accountId)->toBeNull();
});

test('a reversed period is swapped and bad dates fall back', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-03-20 10:00:00', 'Africa/Algiers'));

    $swapped = cashFiltersFor(['from' => '2026-03-31', 'to' => '2026-01-01']);
    $bad = cashFiltersFor(['from' => 'nonsense', 'to' => '2026-13-45']);

    expect($swapped->from->toDateString())->toBe('2026-01-01')
        ->and($swapped->to->toDateString())->toBe('2026-03-31')
        ->and($bad->from->toDateString())->toBe('2026-01-01')
        ->and($bad->to->toDateString())->toBe('2026-03-20');
});

test('a user tied to an office is held to it whatever the URL says', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-03-20 10:00:00', 'Africa/Algiers'));

    expect(cashFiltersFor(['office' => '9', 'account' => '4'], 7)->officeId)->toBe(7)
        ->and(cashFiltersFor(['office' => '9', 'account' => '4'])->officeId)->toBe(9)
        ->and(cashFiltersFor(['office' => '9', 'account' => '4'])->accountId)->toBe(4);
});
