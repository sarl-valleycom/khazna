<?php

namespace App\Queries;

use App\Models\Account;
use App\Models\AccountEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * An account's statement for a period: the balance before it, the movements in
 * it with a running balance, and the totals. The account page, the Excel export
 * and the PDF all use this one query, so they can never disagree.
 *
 * Payroll is hidden from anyone without the salary permission: all of a day's
 * payroll movements in one direction collapse into a single "Payroll" line, so
 * neither a name nor an individual salary shows. Balances still add up.
 */
class AccountStatementQuery
{
    private const SIGNED = "CASE WHEN account_entries.direction = 'in' THEN account_entries.amount_cents ELSE -account_entries.amount_cents END";

    /**
     * @return array{startCents: int, totalInCents: int, totalOutCents: int, endCents: int}
     */
    public function summary(Account $account, ?string $from, ?string $to): array
    {
        // Entries before the range move the starting balance.
        $before = $from === null ? 0 : (int) AccountEntry::query()
            ->where('account_id', $account->id)
            ->where('entry_date', '<', $from)
            ->sum(DB::raw(self::SIGNED));
        $start = $account->opening_balance_cents + $before;

        $totals = $this->inRange($account, $from, $to)->selectRaw(
            "COALESCE(SUM(CASE WHEN direction = 'in' THEN amount_cents ELSE 0 END), 0) AS total_in,
             COALESCE(SUM(CASE WHEN direction = 'out' THEN amount_cents ELSE 0 END), 0) AS total_out",
        )->first();
        $totalIn = (int) ($totals->total_in ?? 0);
        $totalOut = (int) ($totals->total_out ?? 0);

        return [
            'startCents' => $start,
            'totalInCents' => $totalIn,
            'totalOutCents' => $totalOut,
            'endCents' => $start + $totalIn - $totalOut,
        ];
    }

    /**
     * The movements of the period with `running_cents`, in order, ending in a
     * unique tie-break (the id) so paging and chunked exports are stable. The
     * window sum runs over the whole period before any page is cut, so the
     * running balance is right on every page and in every chunk.
     *
     * @return Builder<AccountEntry>
     */
    public function entries(Account $account, User $viewer, ?string $from, ?string $to, int $startCents): Builder
    {
        $inRange = $this->inRange($account, $from, $to);

        $source = $viewer->can('view-salaries')
            ? $inRange
            : AccountEntry::query()->fromSub(
                $inRange->toBase()
                    ->selectRaw("MIN(id) AS id, account_id, direction, SUM(amount_cents) AS amount_cents, entry_date, CASE WHEN is_payroll = 1 THEN 'Payroll' ELSE MAX(description) END AS description")
                    ->groupByRaw('account_id, entry_date, direction, is_payroll, CASE WHEN is_payroll = 1 THEN 0 ELSE id END'),
                'account_entries',
            );

        return $source
            ->select('account_entries.*')
            ->selectRaw('? + SUM('.self::SIGNED.') OVER (ORDER BY account_entries.entry_date, account_entries.id) AS running_cents', [$startCents])
            ->orderBy('account_entries.entry_date')
            ->orderBy('account_entries.id');
    }

    /** How many movements the period holds, before payroll is collapsed. */
    public function count(Account $account, ?string $from, ?string $to): int
    {
        return $this->inRange($account, $from, $to)->count();
    }

    /** @return Builder<AccountEntry> */
    private function inRange(Account $account, ?string $from, ?string $to): Builder
    {
        return AccountEntry::query()
            ->where('account_id', $account->id)
            ->when($from !== null, fn (Builder $query) => $query->where('entry_date', '>=', $from))
            ->when($to !== null, fn (Builder $query) => $query->where('entry_date', '<=', $to));
    }
}
