<?php

namespace App\Actions\Clients;

use App\Enums\DueStatus;
use App\Models\Client;
use App\Models\Contract;
use App\Models\Due;
use App\Support\AuditContext;
use Illuminate\Support\Facades\DB;

/**
 * When a client moves to another office, its contracts and its unsettled dues
 * move with it. Visibility is decided by those rows' own office, so leaving them
 * behind would hide the client's money from the office that now owns the client.
 * Settled and canceled dues stay where they were earned.
 */
class CascadeClientOffice
{
    public function handle(Client $client): void
    {
        if ($client->office_id === null) {
            return;
        }

        DB::transaction(function () use ($client): void {
            $reason = __('Client moved to another office.');

            Contract::query()
                ->where('client_id', $client->id)
                ->where('office_id', '!=', $client->office_id)
                ->lockForUpdate()
                ->get()
                ->each(fn (Contract $contract) => app(AuditContext::class)->withReason($reason, fn () => $contract->update(['office_id' => $client->office_id])));

            Due::query()
                ->where('client_id', $client->id)
                ->where('office_id', '!=', $client->office_id)
                ->whereIn('status', [DueStatus::Pending->value, DueStatus::Partial->value])
                ->lockForUpdate()
                ->get()
                ->each(fn (Due $due) => app(AuditContext::class)->withReason($reason, fn () => $due->update(['office_id' => $client->office_id])));
        });
    }
}
