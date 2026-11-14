<?php

namespace App\Actions\Ledger;

use App\Models\AccountEntry;
use App\Models\ManualEntry;
use App\Models\Payment;
use App\Models\Reconciliation;
use App\Models\SalaryAdjustment;
use App\Models\Transfer;
use Illuminate\Support\Facades\DB;

/**
 * The ledger's safety net (FR-ACC-14, NFR-15): every payment, advance, transfer and
 * manual entry must have exactly the entries it should, and every entry must belong
 * to exactly one source that exists. It reads only; the owner decides what to do.
 */
class LedgerVerifier
{
    /**
     * @return array<int, string> One sentence per problem found. Empty means the ledger is consistent.
     */
    public function run(): array
    {
        $counts = AccountEntry::query()
            ->select('source_type', 'source_id', DB::raw('COUNT(*) AS entries'))
            ->groupBy('source_type', 'source_id')
            ->get()
            ->groupBy('source_type')
            ->map(fn ($rows) => $rows->pluck('entries', 'source_id')->map(fn ($count): int => (int) $count));

        $problems = [];
        $expected = [];

        // A payment writes one entry, and a reversal adds one more.
        foreach (Payment::withTrashed()->get(['id', 'reversed_at']) as $payment) {
            $expected[Payment::class][$payment->id] = $payment->isReversed() ? 2 : 1;
        }

        // An advance moves money today; bonuses and deductions write no entry.
        foreach (SalaryAdjustment::query()->get(['id', 'type', 'account_id']) as $adjustment) {
            $expected[SalaryAdjustment::class][$adjustment->id] = $adjustment->account_id !== null ? 1 : 0;
        }

        // A transfer writes two legs and a fee entry when there is a fee, and a reversal doubles that.
        foreach (Transfer::query()->get(['id', 'fee_cents', 'reversed_at']) as $transfer) {
            $expected[Transfer::class][$transfer->id] = (2 + ($transfer->fee_cents > 0 ? 1 : 0)) * ($transfer->isReversed() ? 2 : 1);
        }

        foreach (ManualEntry::query()->get(['id', 'reversed_at']) as $entry) {
            $expected[ManualEntry::class][$entry->id] = $entry->isReversed() ? 2 : 1;
        }

        // A reconciliation writes an adjustment entry only when its difference was recorded.
        foreach (Reconciliation::query()->get(['id', 'adjustment_entry_id']) as $reconciliation) {
            $expected[Reconciliation::class][$reconciliation->id] = $reconciliation->adjustment_entry_id !== null ? 1 : 0;
        }

        $names = [
            Payment::class => 'Payment',
            SalaryAdjustment::class => 'Advance or adjustment',
            Transfer::class => 'Transfer',
            ManualEntry::class => 'Manual entry',
            Reconciliation::class => 'Reconciliation',
        ];

        foreach ($expected as $type => $rows) {
            foreach ($rows as $id => $want) {
                $have = (int) ($counts[$type][$id] ?? 0);

                if ($have !== $want) {
                    $problems[] = __(':source #:id should have :want ledger entries but has :have.', [
                        'source' => $names[$type],
                        'id' => $id,
                        'want' => $want,
                        'have' => $have,
                    ]);
                }
            }
        }

        // Every entry must point at a source that exists. An entry whose source is gone,
        // or of a kind we do not know, is an orphan.
        foreach ($counts as $type => $ids) {
            foreach ($ids as $id => $count) {
                if (! isset($expected[$type][$id])) {
                    $problems[] = isset($names[$type])
                        ? __('Ledger entry for :source #:id, which does not exist.', ['source' => $names[$type], 'id' => $id])
                        : __('Ledger entries point at an unknown source :type #:id.', ['type' => $type, 'id' => $id]);
                }
            }
        }

        return $problems;
    }
}
