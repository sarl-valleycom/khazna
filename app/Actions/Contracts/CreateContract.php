<?php

namespace App\Actions\Contracts;

use App\Actions\Dues\GenerateDues;
use App\Data\ContractInput;
use App\Enums\ContractStatus;
use App\Models\Contract;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class CreateContract
{
    public function __construct(
        private readonly ResolveContractAttributes $attributes,
        private readonly StoreContractAttachment $storeAttachment,
        private readonly GenerateDues $generateDues,
    ) {}

    public function handle(User $actor, ContractInput $input): Contract
    {
        $attributes = $this->attributes->handle($actor, $input);
        $attachment = $input->attachment !== null
            ? $this->storeAttachment->handle($input->attachment)
            : [];

        try {
            return DB::transaction(function () use ($attributes, $attachment): Contract {
                $contract = Contract::query()->create([
                    ...$attributes,
                    ...$attachment,
                    'status' => ContractStatus::Active,
                ]);

                $this->generateDues->forContract($contract);

                return $contract;
            });
        } catch (Throwable $exception) {
            if (isset($attachment['attachment_path'])) {
                Storage::disk('local')->delete($attachment['attachment_path']);
            }

            throw $exception;
        }
    }
}
