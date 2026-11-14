<?php

namespace App\Actions\Contracts;

use App\Data\ContractInput;
use App\Models\Contract;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class UpdateContract
{
    public function __construct(
        private readonly ResolveContractAttributes $attributes,
        private readonly StoreContractAttachment $storeAttachment,
    ) {}

    public function handle(Contract $contract, User $actor, ContractInput $input): Contract
    {
        $attributes = $this->attributes->handle($actor, $input, $contract);
        $oldPath = $contract->attachment_path;
        $attachment = $input->attachment !== null
            ? $this->storeAttachment->handle($input->attachment)
            : [];

        try {
            DB::transaction(fn () => $contract->update([
                ...$attributes,
                ...$attachment,
            ]));
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
