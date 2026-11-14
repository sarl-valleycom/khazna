<?php

namespace App\Actions\Contracts;

use App\Enums\ContractStatus;
use App\Models\Contract;
use App\Support\AuditContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ChangeContractStatus
{
    public function handle(Contract $contract, ContractStatus $target, ?string $reason): Contract
    {
        if (! $contract->status->canTransitionTo($target)) {
            throw ValidationException::withMessages([
                'status' => __('This contract status change is not allowed.'),
            ]);
        }

        $reason = $reason !== null ? trim($reason) : null;
        $reason = $reason === '' ? null : $reason;

        if ($target !== ContractStatus::Active && $reason === null) {
            throw ValidationException::withMessages([
                'reason' => __('Give a reason for pausing or cancelling this contract.'),
            ]);
        }

        DB::transaction(function () use ($contract, $target, $reason): void {
            app(AuditContext::class)->withReason($reason, fn () => $contract->update([
                'status' => $target,
            ]));
        });

        return $contract->refresh();
    }
}
