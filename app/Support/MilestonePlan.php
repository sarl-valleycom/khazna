<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;

/**
 * Turns the milestone rows of a project form into exact centime amounts and
 * refuses any plan that does not add up to the contract amount (FR-CON-05).
 *
 * Rows are either all percentages (which must total 100 and are split with
 * Money::allocate, the remainder going to the last milestone) or all fixed
 * amounts (which must total the contract amount). Mixing the two is refused
 * because the remainder would be ambiguous.
 */
final class MilestonePlan
{
    /**
     * @param  list<array{mode?: string, value?: int|float|string|null}>  $rows  `mode` is "percent" or "amount"; for "amount" the value is centimes.
     * @return list<int> Amount in centimes for each row, in order.
     */
    public static function amounts(array $rows, int $totalCents, string $field = 'milestones'): array
    {
        if ($rows === []) {
            throw ValidationException::withMessages([
                $field => __('Add at least one milestone.'),
            ]);
        }

        $modes = array_unique(array_map(fn (array $row): string => (string) ($row['mode'] ?? 'amount'), $rows));

        if (count($modes) > 1) {
            throw ValidationException::withMessages([
                $field => __('Use either percentages or amounts for every milestone, not a mix.'),
            ]);
        }

        if ($modes[0] === 'percent') {
            return self::fromPercentages($rows, $totalCents, $field);
        }

        $amounts = array_map(fn (array $row): int => (int) ($row['value'] ?? 0), $rows);

        if (in_array(true, array_map(fn (int $amount): bool => $amount <= 0, $amounts), true)) {
            throw ValidationException::withMessages([
                $field => __('Every milestone needs an amount greater than zero.'),
            ]);
        }

        $sum = array_sum($amounts);

        if ($sum !== $totalCents) {
            throw ValidationException::withMessages([
                $field => __('The milestones add up to :sum but the contract is :total. They must match.', [
                    'sum' => Money::fromCents($sum)->format(),
                    'total' => Money::fromCents($totalCents)->format(),
                ]),
            ]);
        }

        return $amounts;
    }

    /**
     * @param  list<array{mode?: string, value?: int|float|string|null}>  $rows
     * @return list<int>
     */
    private static function fromPercentages(array $rows, int $totalCents, string $field): array
    {
        // Thousandths of a percent, as integers, so 33.333 + 33.333 + 33.334 is exactly 100.
        $thousandths = array_map(fn (array $row): int => (int) round((float) ($row['value'] ?? 0) * 1000), $rows);

        if (in_array(true, array_map(fn (int $part): bool => $part <= 0, $thousandths), true)) {
            throw ValidationException::withMessages([
                $field => __('Every milestone needs a percentage greater than zero.'),
            ]);
        }

        if (array_sum($thousandths) !== 100_000) {
            throw ValidationException::withMessages([
                $field => __('The percentages add up to :sum%, not 100%.', [
                    'sum' => rtrim(rtrim(number_format(array_sum($thousandths) / 1000, 3, '.', ''), '0'), '.'),
                ]),
            ]);
        }

        return array_map(
            fn (Money $part): int => $part->cents,
            Money::fromCents($totalCents)->allocate(array_map(fn (int $part): float => $part / 1000, $thousandths)),
        );
    }
}
