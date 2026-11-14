<?php

namespace App\Actions\Ledger;

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
 *
 * Each check is one grouped query in the database, so the cost of a run stays flat in
 * memory however long the history grows: only the problems themselves come back.
 */
class LedgerVerifier
{
    /**
     * What each kind of source is expected to have written, as SQL over the source row `s`.
     *
     * @var array<class-string, array{table: string, label: string, expected: string}>
     */
    private const SOURCES = [
        // A payment writes one entry, and a reversal adds one more.
        Payment::class => ['table' => 'payments', 'label' => 'Payment', 'expected' => 'CASE WHEN s.reversed_at IS NULL THEN 1 ELSE 2 END'],
        // An advance moves money today; bonuses and deductions write no entry.
        SalaryAdjustment::class => ['table' => 'salary_adjustments', 'label' => 'Advance or adjustment', 'expected' => 'CASE WHEN s.account_id IS NOT NULL THEN 1 ELSE 0 END'],
        // A transfer writes two legs and a fee entry when there is a fee; a reversal doubles that.
        Transfer::class => ['table' => 'transfers', 'label' => 'Transfer', 'expected' => '(2 + CASE WHEN s.fee_cents > 0 THEN 1 ELSE 0 END) * CASE WHEN s.reversed_at IS NULL THEN 1 ELSE 2 END'],
        ManualEntry::class => ['table' => 'manual_entries', 'label' => 'Manual entry', 'expected' => 'CASE WHEN s.reversed_at IS NULL THEN 1 ELSE 2 END'],
        // A reconciliation writes an adjustment entry only when its difference was recorded.
        Reconciliation::class => ['table' => 'reconciliations', 'label' => 'Reconciliation', 'expected' => 'CASE WHEN s.adjustment_entry_id IS NOT NULL THEN 1 ELSE 0 END'],
    ];

    /**
     * @return array<int, string> One sentence per problem found. Empty means the ledger is consistent.
     */
    public function run(): array
    {
        $problems = [];

        foreach (self::SOURCES as $type => $spec) {
            // Sources with the wrong number of entries. Soft-deleted rows are still sources.
            $mismatches = DB::select("
                SELECT s.id, ({$spec['expected']}) AS want, COALESCE(e.entries, 0) AS have
                FROM {$spec['table']} s
                LEFT JOIN (
                    SELECT source_id, COUNT(*) AS entries
                    FROM account_entries
                    WHERE source_type = ?
                    GROUP BY source_id
                ) e ON e.source_id = s.id
                WHERE ({$spec['expected']}) <> COALESCE(e.entries, 0)
                ORDER BY s.id
            ", [$type]);

            foreach ($mismatches as $row) {
                $problems[] = __(':source #:id should have :want ledger entries but has :have.', [
                    'source' => $spec['label'],
                    'id' => $row->id,
                    'want' => (int) $row->want,
                    'have' => (int) $row->have,
                ]);
            }

            // Entries whose source row does not exist.
            $orphans = DB::select("
                SELECT e.source_id AS id
                FROM account_entries e
                WHERE e.source_type = ?
                  AND NOT EXISTS (SELECT 1 FROM {$spec['table']} s WHERE s.id = e.source_id)
                GROUP BY e.source_id
                ORDER BY e.source_id
            ", [$type]);

            foreach ($orphans as $row) {
                $problems[] = __('Ledger entry for :source #:id, which does not exist.', ['source' => $spec['label'], 'id' => $row->id]);
            }
        }

        // Entries pointing at a kind of source we do not know.
        $known = array_keys(self::SOURCES);
        $placeholders = implode(',', array_fill(0, count($known), '?'));

        $unknown = DB::select("
            SELECT source_type, source_id
            FROM account_entries
            WHERE source_type NOT IN ({$placeholders})
            GROUP BY source_type, source_id
            ORDER BY source_type, source_id
        ", $known);

        foreach ($unknown as $row) {
            $problems[] = __('Ledger entries point at an unknown source :type #:id.', ['type' => $row->source_type, 'id' => $row->source_id]);
        }

        return $problems;
    }
}
