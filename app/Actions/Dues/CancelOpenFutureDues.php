<?php

namespace App\Actions\Dues;

use App\Enums\DueStatus;
use App\Models\Contract;
use App\Models\Due;
use App\Support\AlgerianDate;
use App\Support\AuditContext;

class CancelOpenFutureDues
{
    public function handle(Contract $contract, string $reason): int
    {
        $reason = trim($reason);

        $dues = Due::query()
            ->where('contract_id', $contract->id)
            ->whereIn('status', [DueStatus::Pending, DueStatus::Partial])
            ->whereDate('due_on', '>', AlgerianDate::todayString())
            ->lockForUpdate()
            ->get();

        foreach ($dues as $due) {
            app(AuditContext::class)->withReason($reason, fn () => $due->update([
                'status' => DueStatus::Canceled,
                'canceled_reason' => $reason,
            ]));
        }

        return $dues->count();
    }
}
