<?php

namespace App\Data;

use App\Enums\ContractType;
use App\Enums\Cycle;
use App\Http\Requests\Contracts\ContractRequest;
use Illuminate\Http\UploadedFile;

final readonly class ContractInput
{
    public function __construct(
        public ContractType $type,
        public ?int $clientId,
        public ?int $supplierId,
        public ?int $categoryId,
        public int $amountCents,
        public string $cycle,
        public string $startsOn,
        public ?string $endsOn,
        public bool $autoRenew,
        public ?string $notes,
        public ?int $officeId,
        public ?UploadedFile $attachment,
        /** @var list<array{label: string, mode: string, value: int|float|string|null, trigger: string, due_on?: string|null}> */
        public array $milestones = [],
    ) {}

    public static function fromRequest(ContractRequest $request): self
    {
        $validated = $request->validated();
        $attachment = $request->file('attachment');

        return new self(
            type: ContractType::from((string) ($validated['type'] ?? ContractType::SaasRenewal->value)),
            clientId: isset($validated['client_id']) ? (int) $validated['client_id'] : null,
            supplierId: isset($validated['supplier_id']) ? (int) $validated['supplier_id'] : null,
            categoryId: isset($validated['category_id']) ? (int) $validated['category_id'] : null,
            amountCents: (int) $validated['amount_cents'],
            cycle: (string) ($validated['cycle'] ?? Cycle::Once->value),
            startsOn: (string) $validated['starts_on'],
            endsOn: isset($validated['ends_on']) ? (string) $validated['ends_on'] : null,
            autoRenew: (bool) ($validated['auto_renew'] ?? false),
            notes: isset($validated['notes']) ? (string) $validated['notes'] : null,
            officeId: isset($validated['office_id']) ? (int) $validated['office_id'] : null,
            attachment: $attachment instanceof UploadedFile ? $attachment : null,
            milestones: array_values($validated['milestones'] ?? []),
        );
    }
}
