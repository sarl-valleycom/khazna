<?php

namespace App\Data;

use App\Models\Payment;
use App\Models\User;
use App\Support\Money;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class PaymentRowData extends Data
{
    public function __construct(
        public int $id,
        public int $dueId,
        public string $dueLabel,
        public int $amountCents,
        public string $amountFormatted,
        public string $paidOn,
        public string $methodLabel,
        public ?string $reference,
        public string $accountName,
        public ?string $recordedBy,
        public bool $hasReceipt,
        public bool $isReversed,
        public ?string $reversalReason,
        public bool $canReverse,
    ) {}

    public static function fromPayment(Payment $payment, User $viewer): self
    {
        $due = $payment->due;

        return new self(
            id: $payment->id,
            dueId: $payment->due_id,
            dueLabel: $due !== null ? $due->kind->label().' · '.($due->due_on?->format('d M Y') ?? __('waiting for delivery')) : '',
            amountCents: $payment->amount_cents,
            amountFormatted: Money::fromCents($payment->amount_cents)->format(),
            paidOn: $payment->paid_on->toDateString(),
            methodLabel: $payment->method->label(),
            reference: $payment->reference,
            accountName: $payment->account->name ?? '',
            recordedBy: $payment->recorder->name ?? null,
            hasReceipt: $payment->receipt_path !== null,
            isReversed: $payment->isReversed(),
            reversalReason: $payment->reversal_reason,
            canReverse: $viewer->can('reverse', $payment),
        );
    }
}
