<?php

use App\Support\MilestonePlan;
use Illuminate\Validation\ValidationException;

test('percentages split the contract exactly, 40 / 30 / 30 of 100,000 DA', function () {
    $amounts = MilestonePlan::amounts([
        ['mode' => 'percent', 'value' => 40],
        ['mode' => 'percent', 'value' => 30],
        ['mode' => 'percent', 'value' => 30],
    ], 10_000_000);

    expect($amounts)->toBe([4_000_000, 3_000_000, 3_000_000]);
});

test('percentages that do not divide evenly send the remainder to the last milestone', function () {
    $amounts = MilestonePlan::amounts([
        ['mode' => 'percent', 'value' => 33.333],
        ['mode' => 'percent', 'value' => 33.333],
        ['mode' => 'percent', 'value' => 33.334],
    ], 100);

    expect(array_sum($amounts))->toBe(100)->and($amounts)->toBe([33, 33, 34]);

    // 1,000.01 DA in thirds: not one centime lost or invented.
    $amounts = MilestonePlan::amounts([
        ['mode' => 'percent', 'value' => 33.333],
        ['mode' => 'percent', 'value' => 33.333],
        ['mode' => 'percent', 'value' => 33.334],
    ], 100_001);

    expect(array_sum($amounts))->toBe(100_001)
        ->and($amounts[2])->toBeGreaterThanOrEqual($amounts[0]);
});

test('fixed amounts must add up to the contract amount', function () {
    expect(MilestonePlan::amounts([
        ['mode' => 'amount', 'value' => 6_000_000],
        ['mode' => 'amount', 'value' => 4_000_000],
    ], 10_000_000))->toBe([6_000_000, 4_000_000]);

    expect(fn () => MilestonePlan::amounts([
        ['mode' => 'amount', 'value' => 6_000_000],
        ['mode' => 'amount', 'value' => 3_999_999],
    ], 10_000_000))->toThrow(ValidationException::class);
});

test('SRS scenario 7: milestones of 40 / 30 / 20 are refused because they do not reach 100 percent', function () {
    try {
        MilestonePlan::amounts([
            ['mode' => 'percent', 'value' => 40],
            ['mode' => 'percent', 'value' => 30],
            ['mode' => 'percent', 'value' => 20],
        ], 10_000_000);
    } catch (ValidationException $exception) {
        expect($exception->errors()['milestones'][0])->toContain('90%');

        return;
    }

    throw new RuntimeException('The plan should have been refused.');
});

test('a plan cannot be empty, mixed, or contain a zero', function () {
    expect(fn () => MilestonePlan::amounts([], 100))->toThrow(ValidationException::class)
        ->and(fn () => MilestonePlan::amounts([['mode' => 'percent', 'value' => 50], ['mode' => 'amount', 'value' => 50]], 100))->toThrow(ValidationException::class)
        ->and(fn () => MilestonePlan::amounts([['mode' => 'amount', 'value' => 0], ['mode' => 'amount', 'value' => 100]], 100))->toThrow(ValidationException::class)
        ->and(fn () => MilestonePlan::amounts([['mode' => 'percent', 'value' => 0], ['mode' => 'percent', 'value' => 100]], 100))->toThrow(ValidationException::class);
});
