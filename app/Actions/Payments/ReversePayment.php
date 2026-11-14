<?php

namespace App\Actions\Payments;

use App\Actions\Ledger\LedgerWriter;
use App\Enums\Direction;
use App\Models\Account;
use App\Models\AccountEntry;
use App\Models\Due;
use App\Models\Payment;
use App\Models\User;
use App\Support\AlgerianDate;
use App\Support\AuditContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReversePayment
{
    public function __construct(
        private readonly LedgerWriter $ledger,
        private readonly RecalculateDue $recalculate,
    ) {}

    public function handle(User $actor, Payment $payment, string $reason): Payment
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw ValidationException::withMessages([
                'reason' => __('Enter a reason for reversing this payment.'),
            ]);
        }

        return DB::transaction(function () use ($actor, $payment, $reason): Payment {
            // Lock the due first, as RecordPayment does, so the two never deadlock.
            $due = Due::query()->lockForUpdate()->findOrFail($payment->due_id);
            $payment = Payment::query()->lockForUpdate()->findOrFail($payment->id);

            if ($payment->isReversed()) {
                throw ValidationException::withMessages([
                    'reason' => __('This payment has already been reversed.'),
                ]);
            }

            app(AuditContext::class)->withReason($reason, fn () => $payment->update([
                'reversed_at' => now(),
                'reversal_reason' => $reason,
                'reversed_by' => $actor->id,
            ]));

            // The original entry stays. An opposite entry cancels it out, filed exactly where the
            // original was: the due's category may have changed since the payment was made.
            $original = AccountEntry::query()
                ->where('source_type', $payment->getMorphClass())
                ->where('source_id', $payment->id)
                ->where('is_reversal', false)
                ->orderBy('id')
                ->first();

            $this->ledger->record(
                account: Account::query()->lockForUpdate()->findOrFail($payment->account_id),
                direction: $due->direction === Direction::In ? Direction::Out : Direction::In,
                amountCents: $payment->amount_cents,
                entryDate: AlgerianDate::today(),
                source: $payment,
                by: $actor,
                description: __('Reversal of payment #:id', ['id' => $payment->id]),
                categoryId: $original !== null ? $original->category_id : $due->category_id,
                isPayroll: $original !== null ? $original->is_payroll : $due->employee_id !== null,
                isReversal: true,
            );

            $this->recalculate->handle($due);

            return $payment->refresh();
        });
    }
}
