<?php

namespace App\Actions\Dues;

use App\Enums\DueStatus;
use App\Models\Contract;
use App\Models\Due;
use App\Support\AlgerianDate;
use App\Support\AuditContext;

class CancelOpenFutureDues
{
    /**
     * Cancel a contract's future dues that nobody has paid anything on. A due
     * with a payment is real money owed, so it stays open: reverse the payment
     * or settle it first.
     */
    public function handle(Contract $contract, string $reason): int
    {
        $reason = trim($reason);

        $dues = Due::query()
            ->where('contract_id', $contract->id)
            ->where('status', DueStatus::Pending)
            ->where('amount_paid_cents', 0)
            ->whereDate('due_on', '>', AlgerianDate::todayString())
            ->lockForUpdate()
            ->get();

        foreach ($dues as $due) {
            app(AuditContext::class)->withReason($reason, fn () => $due->update([
                'status' => DueStatus::Canceled,
                'canceled_reason' => $reason,
                'canceled_by_contract' => true,
            ]));
        }

        return $dues->count();
    }
}
