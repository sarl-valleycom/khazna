<?php

namespace App\Data;

use App\Enums\PaymentMethod;
use App\Http\Requests\Payments\PaymentRequest;
use Illuminate\Http\UploadedFile;

final readonly class PaymentInput
{
    public function __construct(
        public int $amountCents,
        public int $accountId,
        public PaymentMethod $method,
        public string $paidOn,
        public ?string $reference,
        public ?string $notes,
        public ?UploadedFile $receipt,
        public bool $confirmOverpayment,
    ) {}

    public static function fromRequest(PaymentRequest $request): self
    {
        $validated = $request->validated();

        return new self(
            amountCents: (int) $validated['amount_cents'],
            accountId: (int) $validated['account_id'],
            method: PaymentMethod::from((string) $validated['method']),
            paidOn: (string) $validated['paid_on'],
            reference: isset($validated['reference']) && $validated['reference'] !== '' ? (string) $validated['reference'] : null,
            notes: isset($validated['notes']) && $validated['notes'] !== '' ? (string) $validated['notes'] : null,
            receipt: $request->file('receipt'),
            confirmOverpayment: (bool) ($validated['confirm_overpayment'] ?? false),
        );
    }
}
