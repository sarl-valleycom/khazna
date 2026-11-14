<?php

namespace App\Actions\Milestones;

use App\Enums\DueStatus;
use App\Models\Contract;
use App\Models\Due;
use App\Support\AuditContext;

class ReviveMilestoneDues
{
    /**
     * A project is billed by its milestones, not by a cycle, so reactivating it cannot
     * regenerate anything: it brings back exactly the unpaid milestone dues that pausing
     * or canceling the contract withdrew. A due someone canceled by hand stays canceled.
     */
    public function handle(Contract $contract): int
    {
        $dues = Due::query()
            ->where('contract_id', $contract->id)
            ->whereNotNull('milestone_id')
            ->where('status', DueStatus::Canceled)
            ->where('canceled_by_contract', true)
            ->where('amount_paid_cents', 0)
            ->lockForUpdate()
            ->get();

        foreach ($dues as $due) {
            app(AuditContext::class)->withReason(__('Contract reactivated'), fn () => $due->update([
                'status' => DueStatus::Pending,
                'canceled_reason' => null,
                'canceled_by_contract' => false,
            ]));
        }

        return $dues->count();
    }
}
