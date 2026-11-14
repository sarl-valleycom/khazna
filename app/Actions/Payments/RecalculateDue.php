<?php

namespace App\Actions\Payments;

use App\Enums\DueStatus;
use App\Models\Due;
use App\Models\Payment;

/**
 * Derives a due's paid amount and status from its non-reversed payments, so the
 * stored figures can never drift from the payments themselves. Call it inside
 * the transaction that changed the payments, with the due already locked.
 */
class RecalculateDue
{
    public function handle(Due $due): Due
    {
        $paid = (int) Payment::query()
            ->where('due_id', $due->id)
            ->whereNull('reversed_at')
            ->sum('amount_cents');

        $status = match (true) {
            $due->status === DueStatus::Canceled => DueStatus::Canceled,
            $paid >= $due->amount_due_cents && $due->amount_due_cents > 0 => DueStatus::Paid,
            $paid > 0 => DueStatus::Partial,
            default => DueStatus::Pending,
        };

        $due->update([
            'amount_paid_cents' => $paid,
            'status' => $status,
        ]);

        return $due;
    }
}
