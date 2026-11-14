<?php

namespace App\Queries;

use App\Models\Account;
use App\Models\AccountEntry;
use App\Models\Office;
use App\Models\Transfer;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Cash reports (FR-ACC-10, FR-OFF-05). They read the ledger, so they count money that
 * actually moved, never what is merely due, and they leave out the legs of transfers
 * (not income, not expense). A reversal is netted against the direction it undoes, so
 * a payment that was reversed does not count as money in and money out.
 *
 * The "cash result" is money in minus money out. It is not an accounting profit.
 */
class CashReportQuery
{
    /**
     * @return array{
     *     from: string, to: string, months: array<int, string>,
     *     rows: array<int, array{direction: string, category: string, months: array<string, int>, totalCents: int}>,
     *     totals: array{in: array<string, int>, out: array<string, int>, result: array<string, int>, inCents: int, outCents: int, resultCents: int},
     *     offices: array<int, array{officeId: int, officeName: string, inCents: int, outCents: int, resultCents: int, balanceCents: int, sentCents: int, receivedCents: int}>
     * }
     */
    public function handle(User $user, CarbonImmutable $from, CarbonImmutable $to, ?int $officeId, ?int $accountId): array
    {
        $accounts = Account::query()
            ->visibleTo($user)
            ->withBalance()
            ->with('office:id,name')
            ->when($officeId !== null, fn ($query) => $query->where('office_id', $officeId))
            ->when($accountId !== null, fn ($query) => $query->whereKey($accountId))
            ->get();

        $entries = AccountEntry::query()
            ->join('accounts', 'accounts.id', '=', 'account_entries.account_id')
            ->leftJoin('categories', 'categories.id', '=', 'account_entries.category_id')
            ->whereIn('account_entries.account_id', $accounts->modelKeys())
            ->where('account_entries.is_transfer', false)
            ->whereDate('account_entries.entry_date', '>=', $from->toDateString())
            ->whereDate('account_entries.entry_date', '<=', $to->toDateString())
            ->selectRaw("
                DATE_FORMAT(account_entries.entry_date, '%Y-%m') AS month,
                accounts.office_id AS office_id,
                CASE
                    WHEN account_entries.direction = 'in' AND account_entries.is_reversal = 0 THEN 'in'
                    WHEN account_entries.direction = 'out' AND account_entries.is_reversal = 1 THEN 'in'
                    ELSE 'out'
                END AS bucket,
                CASE WHEN account_entries.is_reversal = 1 THEN -1 ELSE 1 END AS sign,
                categories.name AS category_name,
                SUM(account_entries.amount_cents) AS amount
            ")
            ->groupByRaw('month, accounts.office_id, bucket, sign, categories.name')
            ->toBase()
            ->get();

        $months = $this->months($from, $to);
        $rows = [];
        $totals = ['in' => array_fill_keys($months, 0), 'out' => array_fill_keys($months, 0)];
        $perOffice = [];

        foreach ($entries as $entry) {
            $amount = (int) $entry->amount * (int) $entry->sign;
            $bucket = (string) $entry->bucket;
            $category = $entry->category_name ?? ($bucket === 'in' ? __('Client payments') : __('Uncategorized'));
            $key = $bucket.'|'.$category;

            $rows[$key] ??= ['direction' => $bucket, 'category' => $category, 'months' => array_fill_keys($months, 0), 'totalCents' => 0];
            $rows[$key]['months'][$entry->month] += $amount;
            $rows[$key]['totalCents'] += $amount;
            $totals[$bucket][$entry->month] += $amount;
            $perOffice[(int) $entry->office_id][$bucket] = ($perOffice[(int) $entry->office_id][$bucket] ?? 0) + $amount;
        }

        $rows = collect($rows)
            ->sortBy([['direction', 'desc'], ['category', 'asc']])
            ->values()
            ->all();

        $result = [];

        foreach ($months as $month) {
            $result[$month] = $totals['in'][$month] - $totals['out'][$month];
        }

        $inTotal = array_sum($totals['in']);
        $outTotal = array_sum($totals['out']);

        return [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'months' => $months,
            'rows' => $rows,
            'totals' => [
                'in' => $totals['in'],
                'out' => $totals['out'],
                'result' => $result,
                'inCents' => $inTotal,
                'outCents' => $outTotal,
                'resultCents' => $inTotal - $outTotal,
            ],
            'offices' => $this->offices($user, $from, $to, $officeId, $accounts, $perOffice),
        ];
    }

    /**
     * @param  Collection<int, Account>  $accounts
     * @param  array<int, array<string, int>>  $perOffice
     * @return array<int, array{officeId: int, officeName: string, inCents: int, outCents: int, resultCents: int, balanceCents: int, sentCents: int, receivedCents: int}>
     */
    private function offices(User $user, CarbonImmutable $from, CarbonImmutable $to, ?int $officeId, Collection $accounts, array $perOffice): array
    {
        // Transfers that crossed offices in the period. A reversed one nets to nothing, so it is left out.
        $sent = [];
        $received = [];

        Transfer::query()
            ->with(['from:id,office_id', 'to:id,office_id'])
            ->whereNull('reversed_at')
            ->whereDate('transfer_date', '>=', $from->toDateString())
            ->whereDate('transfer_date', '<=', $to->toDateString())
            ->get()
            ->filter(fn (Transfer $transfer): bool => $transfer->isInterOffice())
            ->each(function (Transfer $transfer) use (&$sent, &$received): void {
                $sent[$transfer->from->office_id ?? 0] = ($sent[$transfer->from->office_id ?? 0] ?? 0) + $transfer->amount_cents;
                $received[$transfer->to->office_id ?? 0] = ($received[$transfer->to->office_id ?? 0] ?? 0) + $transfer->amount_cents;
            });

        return Office::query()
            ->visibleTo($user)
            ->when($officeId !== null, fn ($query) => $query->whereKey($officeId))
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(function (Office $office) use ($accounts, $perOffice, $sent, $received): array {
                $in = (int) ($perOffice[$office->id]['in'] ?? 0);
                $out = (int) ($perOffice[$office->id]['out'] ?? 0);

                return [
                    'officeId' => $office->id,
                    'officeName' => $office->name,
                    'inCents' => $in,
                    'outCents' => $out,
                    'resultCents' => $in - $out,
                    'balanceCents' => (int) $accounts->where('office_id', $office->id)->where('active', true)->sum('balance_cents'),
                    'sentCents' => (int) ($sent[$office->id] ?? 0),
                    'receivedCents' => (int) ($received[$office->id] ?? 0),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function months(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $months = [];

        for ($cursor = $from->startOfMonth(); $cursor->lte($to); $cursor = $cursor->addMonth()) {
            $months[] = $cursor->format('Y-m');
        }

        return $months;
    }
}
