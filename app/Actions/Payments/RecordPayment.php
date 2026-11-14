<?php

namespace App\Actions\Payments;

use App\Actions\Ledger\LedgerWriter;
use App\Data\PaymentInput;
use App\Models\Account;
use App\Models\Due;
use App\Models\Payment;
use App\Models\User;
use App\Support\AlgerianDate;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class RecordPayment
{
    public function __construct(
        private readonly LedgerWriter $ledger,
        private readonly RecalculateDue $recalculate,
        private readonly StorePaymentReceipt $storeReceipt,
    ) {}

    public function handle(User $actor, Due $due, PaymentInput $input): Payment
    {
        $receipt = $input->receipt !== null ? $this->storeReceipt->handle($input->receipt) : [];

        try {
            return DB::transaction(fn (): Payment => $this->record($actor, $due, $input, $receipt));
        } catch (Throwable $exception) {
            if (isset($receipt['receipt_path'])) {
                Storage::disk('local')->delete($receipt['receipt_path']);
            }

            throw $exception;
        }
    }

    /**
     * @param  array<string, string>  $receipt
     */
    private function record(User $actor, Due $due, PaymentInput $input, array $receipt): Payment
    {
        // Serialize concurrent payments on the same due before reading its balance.
        $due = Due::query()->lockForUpdate()->findOrFail($due->id);

        if (! $due->status->isOpen()) {
            throw ValidationException::withMessages([
                'amount_cents' => __('This due is already settled or canceled, so it cannot receive a payment.'),
            ]);
        }

        $paidOn = AlgerianDate::toAlgiers($input->paidOn)->startOfDay();

        if ($paidOn->gt(AlgerianDate::today())) {
            throw ValidationException::withMessages([
                'paid_on' => __('The payment date cannot be in the future.'),
            ]);
        }

        $account = Account::query()
            ->visibleTo($actor)
            ->where('active', true)
            ->lockForUpdate()
            ->find($input->accountId);

        if ($account === null) {
            throw ValidationException::withMessages([
                'account_id' => __('Choose one of the accounts available to you.'),
            ]);
        }

        $remaining = $due->remainingCents();

        if ($input->amountCents > $remaining && ! $input->confirmOverpayment) {
            throw ValidationException::withMessages([
                'confirm_overpayment' => __('This is :over more than the remaining :remaining. Confirm to record it as a credit on the due.', [
                    'over' => Money::fromCents($input->amountCents - $remaining)->format(),
                    'remaining' => Money::fromCents($remaining)->format(),
                ]),
            ]);
        }

        $payment = Payment::query()->create([
            ...$receipt,
            'due_id' => $due->id,
            'account_id' => $account->id,
            'amount_cents' => $input->amountCents,
            'paid_on' => $paidOn->toDateString(),
            'method' => $input->method,
            'reference' => $input->reference,
            'notes' => $input->notes,
            'recorded_by' => $actor->id,
        ]);

        $this->ledger->record(
            account: $account,
            direction: $due->direction,
            amountCents: $payment->amount_cents,
            entryDate: $paidOn,
            source: $payment,
            by: $actor,
            description: __('Payment: :kind due :date', [
                'kind' => $due->kind->label(),
                'date' => $due->due_on?->format('d M Y') ?? __('on delivery'),
            ]),
            dateField: 'paid_on',
            categoryId: $due->category_id,
            isPayroll: $due->employee_id !== null,
        );

        $this->recalculate->handle($due);

        return $payment;
    }
}
