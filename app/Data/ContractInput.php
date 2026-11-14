<?php

namespace App\Data;

use App\Http\Requests\Contracts\ContractRequest;
use Illuminate\Http\UploadedFile;

final readonly class ContractInput
{
    public function __construct(
        public int $clientId,
        public int $amountCents,
        public string $cycle,
        public string $startsOn,
        public ?string $endsOn,
        public bool $autoRenew,
        public ?string $notes,
        public ?int $officeId,
        public ?UploadedFile $attachment,
    ) {}

    public static function fromRequest(ContractRequest $request): self
    {
        $validated = $request->validated();
        $attachment = $request->file('attachment');

        return new self(
            clientId: (int) $validated['client_id'],
            amountCents: (int) $validated['amount_cents'],
            cycle: (string) $validated['cycle'],
            startsOn: (string) $validated['starts_on'],
            endsOn: isset($validated['ends_on']) ? (string) $validated['ends_on'] : null,
            autoRenew: (bool) $validated['auto_renew'],
            notes: isset($validated['notes']) ? (string) $validated['notes'] : null,
            officeId: isset($validated['office_id']) ? (int) $validated['office_id'] : null,
            attachment: $attachment instanceof UploadedFile ? $attachment : null,
        );
    }
}
