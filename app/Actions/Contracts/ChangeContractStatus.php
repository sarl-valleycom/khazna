<?php

namespace App\Actions\Contracts;

use App\Actions\Dues\CancelOpenFutureDues;
use App\Actions\Dues\GenerateDues;
use App\Enums\ContractStatus;
use App\Models\Contract;
use App\Support\AuditContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ChangeContractStatus
{
    public function __construct(
        private readonly CancelOpenFutureDues $cancelDues,
        private readonly GenerateDues $generateDues,
    ) {}

    public function handle(Contract $contract, ContractStatus $target, ?string $reason): Contract
    {
        if (! $contract->status->canTransitionTo($target)) {
            throw ValidationException::withMessages([
                'status' => __('A contract cannot move to that status from where it is now.'),
            ]);
        }

        $reason = $reason !== null ? trim($reason) : null;
        $reason = $reason === '' ? null : $reason;

        if ($target !== ContractStatus::Active && $reason === null) {
            throw ValidationException::withMessages([
                'reason' => __('Give a reason for pausing or canceling this contract.'),
            ]);
        }

        DB::transaction(function () use ($contract, $target, $reason): void {
            app(AuditContext::class)->withReason($reason, fn () => $contract->update([
                'status' => $target,
            ]));

            if ($target === ContractStatus::Active) {
                $this->generateDues->forContract($contract, revive: true);

                return;
            }

            $this->cancelDues->handle($contract, (string) $reason);
        });

        return $contract->refresh();
    }
}
