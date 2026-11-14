<?php

namespace App\Actions\Contracts;

use App\Actions\Dues\CancelOpenFutureDues;
use App\Actions\Dues\GenerateDues;
use App\Data\ContractInput;
use App\Enums\ContractType;
use App\Enums\DueStatus;
use App\Models\Contract;
use App\Models\Due;
use App\Models\User;
use App\Support\AuditContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class UpdateContract
{
    /** Fields that decide which dues exist. The amount is deliberately not one of them (FR-CON-04). */
    private const SCHEDULE_FIELDS = ['cycle', 'starts_on', 'ends_on', 'auto_renew'];

    public function __construct(
        private readonly ResolveContractAttributes $attributes,
        private readonly StoreContractAttachment $storeAttachment,
        private readonly CancelOpenFutureDues $cancelDues,
        private readonly GenerateDues $generateDues,
    ) {}

    public function handle(Contract $contract, User $actor, ContractInput $input): Contract
    {
        $attributes = $this->attributes->handle($actor, $input, $contract);
        $oldPath = $contract->attachment_path;
        $attachment = $input->attachment !== null
            ? $this->storeAttachment->handle($input->attachment)
            : [];

        try {
            DB::transaction(function () use ($contract, $attributes, $attachment): void {
                $contract->fill([...$attributes, ...$attachment]);
                // A project has no cycle to reschedule: its dues are its milestones.
                $rescheduled = $contract->type !== ContractType::CustomProject
                    && $contract->isDirty(self::SCHEDULE_FIELDS);
                $recategorized = $contract->isDirty('category_id');
                $moved = $contract->isDirty('office_id');
                $contract->save();

                // Dues already generated carry the contract's category and office, and a payment
                // copies the category onto the ledger. Keep the ones still open in step, so cash
                // reports file them under the right category. Settled dues are history.
                if ($recategorized || $moved) {
                    Due::query()
                        ->where('contract_id', $contract->id)
                        ->whereIn('status', [DueStatus::Pending->value, DueStatus::Partial->value])
                        ->lockForUpdate()
                        ->get()
                        ->each(fn (Due $due) => app(AuditContext::class)->withReason(__('Contract updated'), fn () => $due->update(array_filter([
                            'category_id' => $recategorized ? $contract->category_id : null,
                            'office_id' => $moved ? $contract->office_id : null,
                        ], fn ($value): bool => $value !== null))));
                }

                // A new schedule replaces the old one: unpaid future dues of the old
                // schedule are canceled, and the periods the new one still bills are
                // brought back. Paid and part-paid dues are never touched.
                if ($rescheduled) {
                    $this->cancelDues->handle($contract, __('Contract schedule changed.'));
                    $this->generateDues->forContract($contract, revive: true);
                }
            });
        } catch (Throwable $exception) {
            if (isset($attachment['attachment_path'])) {
                Storage::disk('local')->delete($attachment['attachment_path']);
            }

            throw $exception;
        }

        if (isset($attachment['attachment_path']) && $oldPath !== null) {
            Storage::disk('local')->delete($oldPath);
        }

        return $contract->refresh();
    }
}
